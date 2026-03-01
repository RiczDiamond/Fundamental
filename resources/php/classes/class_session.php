<?php

class Session {

    private $link;
    private $table = 'mw_sessions';
    private $cookie_table = 'mw_cookies';
    private $timeout = 3600; // 1 uur standaard
    private $session_name = 'mw_session';
    private $cookie;
    private $data = [];
    private $started = false;
    private $session_id;

    public $auth = 'auth';
    public $id;

    public function __construct($link, $cookie = null) {
        $this->link = $link;
        $this->cookie = $cookie;
        
        // Start sessie automatisch
        $this->start();
    }

    /**
     * Start sessie
     */
    public function start() {
        if ($this->started) {
            return true;
        }

        try {
            // Check of er een sessie cookie is
            $sessionId = $this->getSessionCookie();
            
            if ($sessionId) {
                // Laad bestaande sessie
                if ($this->load($sessionId)) {
                    $this->started = true;
                    return true;
                }
            }

            // Maak nieuwe sessie
            $this->create();
            $this->started = true;
            
            return true;

        } catch (Exception $e) {
            error_log("Session start error: " . $e->getMessage());
            // Fallback naar native PHP sessie
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return false;
        }
    }

    /**
     * Maak nieuwe sessie aan
     */
    private function create() {
        // Genereer cryptografisch veilige sessie ID
        $this->session_id = $this->generateSessionId();
        $this->id = $this->session_id;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Sla op in database
        $sql = "INSERT INTO {$this->table} 
                (session_id, ip, user_agent, data, created_at, last_activity, expires_at) 
                VALUES 
                (:session_id, :ip, :user_agent, :data, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL :timeout SECOND))";
        
        $stmt = $this->link->prepare($sql);
        $stmt->execute([
            ':session_id' => $this->session_id,
            ':ip' => $ip,
            ':user_agent' => $userAgent,
            ':data' => json_encode([]),
            ':timeout' => $this->timeout
        ]);

        // Zet sessie cookie
        $this->setSessionCookie($this->session_id);
        
        // Initialiseer lege data array
        $this->data = [];
        
        return true;
    }

    /**
     * Laad bestaande sessie
     */
    private function load($sessionId) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE session_id = :session_id 
                    AND expires_at > NOW()
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return false;
            }

            // Validatie: IP en User Agent (optioneel, tegen session hijacking)
            if ($this->validateSession($session)) {
                $this->session_id = $session['session_id'];
                $this->id = $this->session_id;
                $this->data = json_decode($session['data'], true) ?? [];
                
                // Update last activity
                $this->touch();
                
                return true;
            }

            // Validatie mislukt, verwijder verdachte sessie
            $this->destroy($sessionId);
            return false;

        } catch (PDOException $e) {
            error_log("Session load error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valideer sessie tegen hijacking
     */
    private function validateSession($session) {
        // Strict mode: IP moet matchen
        if ($session['ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            // Log verdachte activiteit
            $this->logSuspiciousActivity('IP mismatch', $session);
            return false;
        }

        // User agent moet matchen (kan veranderen bij browser updates, dus optioneel)
        // if ($session['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        //     $this->logSuspiciousActivity('User agent mismatch', $session);
        //     return false;
        // }

        return true;
    }

    /**
     * Sla sessie data op
     */
    public function save() {
        if (!$this->started) {
            return false;
        }

        try {
            $sql = "UPDATE {$this->table} 
                    SET data = :data, 
                        last_activity = NOW(),
                        expires_at = DATE_ADD(NOW(), INTERVAL :timeout SECOND)
                    WHERE session_id = :session_id";
            
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([
                ':data' => json_encode($this->data),
                ':timeout' => $this->timeout,
                ':session_id' => $this->session_id
            ]);

        } catch (PDOException $e) {
            error_log("Session save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vernieuw sessie (regenerate ID voor security)
     */
    public function regenerate($deleteOld = true) {
        if (!$this->started) {
            return false;
        }

        $oldSessionId = $this->session_id;
        $newSessionId = $this->generateSessionId();

        try {
            if ($deleteOld) {
                // Verwijder oude sessie
                $sql = "DELETE FROM {$this->table} WHERE session_id = :session_id";
                $stmt = $this->link->prepare($sql);
                $stmt->execute([':session_id' => $oldSessionId]);
            } else {
                // Update oude sessie naar nieuwe ID
                $sql = "UPDATE {$this->table} SET session_id = :new_id WHERE session_id = :old_id";
                $stmt = $this->link->prepare($sql);
                $stmt->execute([
                    ':new_id' => $newSessionId,
                    ':old_id' => $oldSessionId
                ]);
            }

            // Maak nieuwe sessie entry als we de oude verwijderd hebben
            if ($deleteOld) {
                $sql = "INSERT INTO {$this->table} 
                        (session_id, ip, user_agent, data, created_at, last_activity, expires_at) 
                        VALUES 
                        (:session_id, :ip, :user_agent, :data, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL :timeout SECOND))";
                
                $stmt = $this->link->prepare($sql);
                $stmt->execute([
                    ':session_id' => $newSessionId,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    ':data' => json_encode($this->data),
                    ':timeout' => $this->timeout
                ]);
            }

            $this->session_id = $newSessionId;
            $this->id = $newSessionId;
            
            // Update cookie
            $this->setSessionCookie($newSessionId);

            return true;

        } catch (PDOException $e) {
            error_log("Session regenerate error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vernietig sessie
     */
    public function destroy($sessionId = null) {
        $id = $sessionId ?? $this->session_id;

        try {
            // Verwijder uit database
            $sql = "DELETE FROM {$this->table} WHERE session_id = :session_id";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':session_id' => $id]);

            // Verwijder cookie
            if (!$sessionId || $sessionId === $this->session_id) {
                $this->deleteSessionCookie();
                $this->data = [];
                $this->started = false;
            }

            return true;

        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set sessie variabele
     */
    public function set($key, $value) {
        if (!$this->started) {
            $this->start();
        }

        $this->data[$key] = $value;
        return $this->save();
    }

    /**
     * Get sessie variabele
     */
    public function get($key, $default = null) {
        if (!$this->started) {
            $this->start();
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Check of sessie variabele bestaat
     */
    public function has($key) {
        if (!$this->started) {
            $this->start();
        }

        return isset($this->data[$key]);
    }

    /**
     * Verwijder sessie variabele
     */
    public function remove($key) {
        if (!$this->started) {
            return false;
        }

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
            return $this->save();
        }

        return true;
    }

    /**
     * Haal alle sessie data
     */
    public function all() {
        if (!$this->started) {
            $this->start();
        }

        return $this->data;
    }

    /**
     * Leeg sessie (behoudt sessie ID)
     */
    public function clear() {
        if (!$this->started) {
            return false;
        }

        $this->data = [];
        return $this->save();
    }

    /**
     * Flash message (1x ophalen, dan verwijderen)
     */
    public function flash($key, $value = null) {
        if ($value !== null) {
            // Set flash data
            $flashKey = '_flash_' . $key;
            return $this->set($flashKey, [
                'value' => $value,
                'time' => time()
            ]);
        } else {
            // Get en verwijder
            $flashKey = '_flash_' . $key;
            $flash = $this->get($flashKey);
            
            if ($flash) {
                $this->remove($flashKey);
                return $flash['value'];
            }
            
            return null;
        }
    }

    /**
     * Set timeout
     */
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
        
        // Update huidige sessie
        if ($this->started) {
            $this->touch();
        }
        
        return $this;
    }

    /**
     * Get timeout
     */
    public function getTimeout() {
        return $this->timeout;
    }

    /**
     * Check of sessie actief is
     */
    public function isActive() {
        return $this->started;
    }

    /**
     * Get sessie ID
     */
    public function getId() {
        return $this->session_id;
    }

    /**
     * Set auth data (snelkoppeling voor login)
     */
    public function setAuth($userId, $data = []) {
        $authData = array_merge([
            'user_id' => $userId,
            'login_time' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ], $data);

        return $this->set($this->auth, $authData);
    }

    /**
     * Get auth data
     */
    public function getAuth($key = null) {
        $auth = $this->get($this->auth);
        
        if (!$auth) {
            return null;
        }

        if ($key) {
            return $auth[$key] ?? null;
        }

        return $auth;
    }

    /**
     * Check of gebruiker is ingelogd
     */
    public function isAuthenticated() {
        return $this->getAuth('user_id') !== null;
    }

    /**
     * Logout (verwijder auth)
     */
    public function logout() {
        $this->remove($this->auth);
        $this->regenerate(true);
        return true;
    }

    /**
     * Update last activity timestamp
     */
    private function touch() {
        try {
            $sql = "UPDATE {$this->table} 
                    SET last_activity = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL :timeout SECOND)
                    WHERE session_id = :session_id";
            
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([
                ':timeout' => $this->timeout,
                ':session_id' => $this->session_id
            ]);

        } catch (PDOException $e) {
            error_log("Session touch error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper: Genereer sessie ID
     */
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Helper: Get sessie cookie
     */
    private function getSessionCookie() {
        // Gebruik cookie class als beschikbaar
        if ($this->cookie) {
            return $this->cookie->get($this->session_name);
        }

        // Fallback naar native
        return $_COOKIE[$this->session_name] ?? null;
    }

    /**
     * Helper: Set sessie cookie
     */
    private function setSessionCookie($sessionId) {
        $expires = time() + $this->timeout;
        
        // Gebruik cookie class als beschikbaar (veiliger)
        if ($this->cookie) {
            $this->cookie->set($this->session_name, $sessionId, [
                'expires' => $this->timeout,
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Strict'
            ]);
        } else {
            // Fallback naar native
            setcookie($this->session_name, $sessionId, [
                'expires' => $expires,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }

    /**
     * Helper: Delete sessie cookie
     */
    private function deleteSessionCookie() {
        if ($this->cookie) {
            $this->cookie->delete($this->session_name);
        } else {
            setcookie($this->session_name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }

    /**
     * Log verdachte activiteit
     */
    private function logSuspiciousActivity($reason, $session) {
        try {
            $sql = "INSERT INTO mw_security_log 
                    (event_type, session_id, reason, ip, user_agent, created_at) 
                    VALUES 
                    ('session_hijack_attempt', :session_id, :reason, :ip, :user_agent, NOW())";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':session_id' => $session['session_id'],
                ':reason' => $reason,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

        } catch (PDOException $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }

    /**
     * Garbage collection (verwijder oude sessies)
     */
    public function gc($maxLifetime = null) {
        $lifetime = $maxLifetime ?? $this->timeout;
        
        try {
            $sql = "DELETE FROM {$this->table} 
                    WHERE expires_at < NOW() 
                    OR last_activity < DATE_SUB(NOW(), INTERVAL :lifetime SECOND)";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':lifetime' => $lifetime]);
            
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Session GC error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get statistieken
     */
    public function stats() {
        try {
            $stats = [];
            
            // Actieve sessies
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE expires_at > NOW()";
            $stmt = $this->link->query($sql);
            $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Totaal vandaag
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE created_at > CURDATE()";
            $stmt = $this->link->query($sql);
            $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Gemiddelde leeftijd
            $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_age 
                    FROM {$this->table} WHERE expires_at > NOW()";
            $stmt = $this->link->query($sql);
            $stats['avg_age_minutes'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_age'] ?? 0);
            
            return $stats;

        } catch (PDOException $e) {
            error_log("Session stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Destructor: auto-save
     */
    public function __destruct() {
        if ($this->started) {
            $this->save();
        }
    }
}