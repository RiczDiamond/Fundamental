<?php

class Logging
{
    private $link;           
    private $timeout = 3600; 

    public $auth = 'users';    
    public $table;             
    public $id;                
    public $userId;            
    public $blockedBots = [
        'BadBot', 'Scraper', 'AhrefsBot', 'MJ12bot', 'Baiduspider'
    ];

    // Bestandsextensies en paths die we niet als requests willen loggen
    private $staticExtensions = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'map'
    ];

    // Sampling: only store non-suspicious payloads at this rate (1 in SAMPLE_RATE)
    private $sampleRate = 100; // 1% by default

    // Dedupe window in minutes: merge similar payloads within this window
    private $dedupeWindowMinutes = 10;

    // Pagina’s waar geen blocking/logging nodig is
    private $excludedPaths = [
        '/dashboard',
        '/auth',        // login, register pages
        '/settings'
    ];

    public function __construct($link, $table = 'logs')
    {
        $this->link = $link;
        $this->table = $table;
        $this->id = session_id();
    }

    /**
     * Check of het een legitieme gebruiker is
     */
    public function isLegitUser(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Googlebot mag altijd
        if (stripos($ua, 'Googlebot') !== false) {
            return true;
        }

        // Blokkeer bekende slechte bots
        foreach ($this->blockedBots as $bot) {
            if (stripos($ua, $bot) !== false) {
                return false;
            }
        }

        // Simpele check op menselijke user agent
        return !empty($ua) && strlen($ua) > 10;
    }

    /**
     * Controleer of de huidige pagina is uitgesloten van logging/blocking
     */
    private function isExcludedPath(): bool
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        foreach ($this->excludedPaths as $path) {
            if (stripos($currentPath, $path) === 0) {
                return true;
            }
        }
        // Skip common static assets (favicon, images, css, js etc.)
        $ext = pathinfo($currentPath, PATHINFO_EXTENSION);
        if ($ext && in_array(strtolower($ext), $this->staticExtensions, true)) {
            return true;
        }
        return false;
    }

    /**
     * Log request in de database
     */
    public function logRequest(): void
    {
        if ($this->isExcludedPath()) {
            return; // geen logging voor uitgesloten pagina's
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $timestamp = date('Y-m-d H:i:s');

        // Extra context die we misschien willen onderzoeken
        $get = $_GET ?? [];
        $post = $_POST ?? [];
        $rawBody = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        // Detect suspicious content in URI, params or body
        $suspicious = $this->detectSuspicious($requestUri, $get, $post, $rawBody, $ua);

        // Bouw dynamische insert afhankelijk van welke kolommen in de logs-tabel bestaan
        $baseCols = ['user_id', 'ip', 'user_agent', 'request_uri', 'referrer', 'method', 'created_at'];
        $extraCols = [];
        if ($suspicious['suspicious']) {
            $extraCols[] = 'payload';
            $extraCols[] = 'is_suspicious';
        }

        $available = $this->getExistingColumns(array_merge($baseCols, $extraCols));
        $cols = [];
        $placeholders = [];
        $values = [];

        foreach ($baseCols as $c) {
            if (in_array($c, $available, true)) {
                $cols[] = $c;
                $placeholders[] = '?';
                switch ($c) {
                    case 'user_id': $values[] = $this->userId; break;
                    case 'ip': $values[] = $ip; break;
                    case 'user_agent': $values[] = $ua; break;
                    case 'request_uri': $values[] = $requestUri; break;
                    case 'referrer': $values[] = $referrer; break;
                    case 'method': $values[] = $method; break;
                    case 'created_at': $values[] = $timestamp; break;
                }
            }
        }

            // Detect suspicious content in URI, params or body
            $suspicious = $this->detectSuspicious($requestUri, $get, $post, $rawBody, $ua);

            // Redact sensitive fields from params and body
            $getClean = $this->redactParams($get);
            $postClean = $this->redactParams($post);
            $rawClean = $this->redactRaw($rawBody);

            // Decide whether to include full payload: only when suspicious or by sampling
            $includePayload = $suspicious['suspicious'] || (mt_rand(1, $this->sampleRate) === 1);
            $payloadJson = null;
            $payloadHash = null;
            if ($includePayload) {
                $payloadArr = [
                    'get' => $getClean,
                    'post' => $postClean,
                    'raw' => $rawClean,
                    'headers' => $headers,
                    'reasons' => $suspicious['reasons']
                ];
                $payloadJson = json_encode($payloadArr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                // Truncate to 1024 chars to avoid huge payloads
                if ($payloadJson !== false && strlen($payloadJson) > 1024) {
                    $payloadJson = substr($payloadJson, 0, 1024);
                }
                if ($payloadJson !== false) {
                    $payloadHash = hash('sha256', $payloadJson);
                }
            }

            // Build columns dynamically based on table schema
            $baseCols = ['user_id', 'ip', 'user_agent', 'request_uri', 'referrer', 'method', 'created_at'];
            $extraCols = [];
            if ($payloadJson !== null) {
                $extraCols[] = 'payload';
                $extraCols[] = 'payload_hash';
            }
            if ($suspicious['suspicious']) {
                $extraCols[] = 'is_suspicious';
            }
            $maybeCols = array_merge($baseCols, $extraCols, ['duplicate_count', 'last_seen']);
            $available = $this->getExistingColumns($maybeCols);

            // If we have a payload_hash and dedupe columns exist, try to merge duplicates
            if ($payloadHash && in_array('payload_hash', $available, true) && in_array('duplicate_count', $available, true)) {
                try {
                    $stmt = $this->link->prepare("SELECT id, duplicate_count FROM {$this->table} WHERE payload_hash = ? AND ip = ? AND created_at >= (NOW() - INTERVAL ? MINUTE) LIMIT 1");
                    $stmt->execute([$payloadHash, $ip, $this->dedupeWindowMinutes]);
                    $found = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($found) {
                        $updateCols = [];
                        $updateVals = [];
                        $updateCols[] = 'duplicate_count = duplicate_count + 1';
                        if (in_array('last_seen', $available, true)) {
                            $updateCols[] = 'last_seen = ?';
                            $updateVals[] = date('Y-m-d H:i:s');
                        }
                        $updateVals[] = $found['id'];
                        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $updateCols) . ' WHERE id = ?';
                        $u = $this->link->prepare($sql);
                        $u->execute($updateVals);
                        $this->id = $found['id'];
                        return; // merged, no new insert
                    }
                } catch (Exception $e) {
                    // ignore and fall back to insert
                }
            }

            // Prepare insert columns and values
            $cols = [];
            $placeholders = [];
            $values = [];
            foreach ($baseCols as $c) {
                if (in_array($c, $available, true)) {
                    $cols[] = $c; $placeholders[] = '?';
                    switch ($c) {
                        case 'user_id': $values[] = $this->userId; break;
                        case 'ip': $values[] = $ip; break;
                        case 'user_agent': $values[] = $ua; break;
                        case 'request_uri': $values[] = $requestUri; break;
                        case 'referrer': $values[] = $referrer; break;
                        case 'method': $values[] = $method; break;
                        case 'created_at': $values[] = $timestamp; break;
                    }
                }
            }

            if (in_array('payload', $available, true) && $payloadJson !== null) {
                $cols[] = 'payload'; $placeholders[] = '?'; $values[] = $payloadJson;
            }
            if (in_array('payload_hash', $available, true) && $payloadHash !== null) {
                $cols[] = 'payload_hash'; $placeholders[] = '?'; $values[] = $payloadHash;
            }
            if (in_array('is_suspicious', $available, true) && $suspicious['suspicious']) {
                $cols[] = 'is_suspicious'; $placeholders[] = '?'; $values[] = 1;
            }

            if (empty($cols)) {
                return; // nothing to insert
            }

            $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            try {
                $stmt = $this->link->prepare($sql);
                $stmt->execute($values);
                $this->id = $this->link->lastInsertId();
            } catch (Exception $e) {
                // silent fail
            }
            return;
        }

        // Suspicious query/post keys or values

        /** Redact sensitive keys from arrays */
        private function redactParams(array $params): array
        {
            $sensitive = '/pass|token|auth|secret|credential/i';
            $clean = [];
            foreach ($params as $k => $v) {
                if (preg_match($sensitive, (string)$k)) {
                    $clean[$k] = '[REDACTED]';
                    continue;
                }
                if (is_array($v)) {
                    $clean[$k] = $this->redactParams($v);
                } else {
                    $clean[$k] = $v;
                }
            }
            return $clean;
        }

        /** Redact tokens inside raw body */
        private function redactRaw(string $raw): string
        {
            if (empty($raw)) return '';
            // remove common Authorization headers or tokens in query-like bodies
            $raw = preg_replace('/(Authorization:\s*)([^\r\n]+)/i', '$1[REDACTED]', $raw);
            $raw = preg_replace('/(access_token=)[^&\s]+/i', '$1[REDACTED]', $raw);
            return $raw;
        }
    private function detectSuspicious(string $uri, array $get, array $post, string $rawBody, string $ua): array
    {
        $reasons = [];

        $joined = json_encode([$get, $post]);
        if (preg_match('/("\<script|<script|%3Cscript)/i', $joined)) {
            $reasons[] = 'xss_in_params';
        }

        // Known admin paths that scanners probe
        $adminPaths = ['phpmyadmin', 'wp-login.php', 'wp-admin', 'administrator', 'admin.php'];
        foreach ($adminPaths as $p) {
            if (stripos($uri, $p) !== false) {
                $reasons[] = "probe: $p";
            }
        }

        // Blocked UA list - flag suspicious bots
        foreach ($this->blockedBots as $bot) {
            if (stripos($ua, $bot) !== false) {
                $reasons[] = "bot: $bot";
            }
        }

        return ['suspicious' => !empty($reasons), 'reasons' => $reasons];
    }

    /**
     * Blokkeer verdachte gebruiker of bot
     */
    public function blockRequest(): void
    {
        header('HTTP/1.1 403 Forbidden');
        echo "Access denied.";
        exit;
    }

    /**
     * Main handler: log request en check legitiem
     */
    public function handleRequest($userId = null): void
    {
        $this->userId = $userId;

        // Sla alleen over op uitgesloten paden
        if ($this->isExcludedPath()) {
            return;
        }

        // Log alle overige requests (ook ingelogde gebruikers)
        $this->logRequest();

        // Als anoniem en verdacht: check en block indien nodig
        // (ingelogde gebruikers worden niet automatisch geblokkeerd)
        $isLegit = $this->isLegitUser();
        // Her-run detectSuspicious quickly to decide blocking (could be cached in logRequest)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $get = $_GET ?? [];
        $post = $_POST ?? [];
        $rawBody = file_get_contents('php://input');
        $suspicious = $this->detectSuspicious($requestUri, $get, $post, $rawBody, $_SERVER['HTTP_USER_AGENT'] ?? '');

        if ($this->userId === null && $suspicious['suspicious'] && !$isLegit) {
            $this->blockRequest();
        }
    }

    /**
     * Retrieve which of the requested columns exist in the logs table.
     * Returns array of existing column names.
     */
    private function getExistingColumns(array $cols): array
    {
        try {
            $found = [];
            $stmt = $this->link->query("DESCRIBE {$this->table}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_map(function ($r) { return $r['Field']; }, $rows);
            foreach ($cols as $c) {
                if (in_array($c, $existing, true)) {
                    $found[] = $c;
                }
            }
            return $found;
        } catch (Exception $e) {
            return []; // silent fallback
        }
    }
}