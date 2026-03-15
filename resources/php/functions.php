<?php

    declare(strict_types=1);

    require_once __DIR__ . '/init.php';

    // Start session when this file is included.
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    /**
     * Escaping helpers
     */
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Safe redirect (absolute or relative).
     */
    function mol_safe_redirect(string $url): void
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
            $url = '/' . ltrim($url, '/');
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Nonces for CSRF protection.
     */
    function mol_nonce_field(string $action): void
    {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['nonces'][$action] = $nonce;
        echo '<input type="hidden" name="_nonce" value="' . esc_attr($nonce) . '">';
        echo '<input type="hidden" name="_nonce_action" value="' . esc_attr($action) . '">';
    }

    function mol_require_valid_nonce(string $action): bool
    {
        // Allow nonce fields to come via JSON body (for AJAX requests) or via headers.
        $rawBody = file_get_contents('php://input');
        $jsonBody = is_string($rawBody) ? json_decode($rawBody, true) : null;

        $postedAction = $_POST['_nonce_action']
            ?? ($jsonBody['_nonce_action'] ?? null)
            ?? $_SERVER['HTTP_X_CSRF_ACTION']
            ?? '';

        $postedNonce = $_POST['_nonce']
            ?? ($jsonBody['_nonce'] ?? null)
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if ($postedAction !== $action) {
            return false;
        }

        $stored = $_SESSION['nonces'][$action] ?? null;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        $valid = hash_equals($stored, (string) $postedNonce);
        if ($action !== 'global_csrf') {
            unset($_SESSION['nonces'][$action]);
        }
        return $valid;
    }

    function mol_get_nonce(string $action): string
    {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['nonces'][$action] = $nonce;
        return $nonce;
    }

    /**
     * Validate a password against the current password policy.
     *
     * @param string $password
     * @return string|null Returns null if valid, otherwise returns an error message.
     */
    function mol_validate_password(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Wachtwoord moet minstens 8 tekens lang zijn.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Wachtwoord moet minimaal één hoofdletter bevatten.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Wachtwoord moet minimaal één kleine letter bevatten.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Wachtwoord moet minimaal één cijfer bevatten.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Wachtwoord moet minimaal één speciaal teken bevatten.';
        }
        return null;
    }

    /**
     * Role-based capabilities map.
     * Add/adjust capabilities as needed for your RBAC model.
     *
     * @return array<string, string[]>
     */
    function mol_role_capabilities(): array
    {
        return [
            'admin' => ['view_users', 'manage_users', 'view_audit_log'],
            'editor' => ['view_users'],
            'user' => [],
        ];
    }

    function mol_role_has_capability(string $role, string $capability): bool
    {
        $caps = mol_role_capabilities();
        return in_array($capability, $caps[$role] ?? [], true);
    }

    /**
     * Audit log helper.
     *
     * @param int         $actorId  The user who performed the action.
     * @param int|null    $targetId The target user (optional).
     * @param string      $action   A short action identifier (e.g. "user_created").
     * @param array       $meta     Optional additional context.
     */
    function mol_audit_log(int $actorId, ?int $targetId, string $action, array $meta = []): bool
    {
        try {
            return insert('audit_log', [
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'action' => $action,
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Audit log is best-effort; don't block user operations if it fails.
            $msg = $e->getMessage();
            if (strpos($msg, "doesn't exist") !== false || strpos($msg, '1146') !== false) {
                // Try creating the audit_log table automatically.
                @db_query(
                    "CREATE TABLE IF NOT EXISTS " . table('audit_log') . " (
                        id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
                        actor_id bigint UNSIGNED NOT NULL,
                        target_id bigint UNSIGNED DEFAULT NULL,
                        action varchar(100) NOT NULL,
                        meta text,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY actor_id (actor_id),
                        KEY target_id (target_id),
                        KEY action (action)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
                );

                try {
                    return insert('audit_log', [
                        'actor_id' => $actorId,
                        'target_id' => $targetId,
                        'action' => $action,
                        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable $e2) {
                    error_log('Audit log failed after creating table: ' . $e2->getMessage());
                    return false;
                }
            }

            error_log('Audit log failed: ' . $msg);
            return false;
        }
    }

    /**
     * Send an email using PHPMailer (SMTP) when available.
     *
     * @param string       $recipient
     * @param string       $subject
     * @param string       $message
     * @param string       $from
     * @param string       $from_name
     * @param string       $reply_to
     * @param string       $reply_to_name
     * @param string|array $cc
     * @param string|array $bcc
     * @param string|array $attachments
     *
     * @return true|string True on success or error message on failure.
     */
    function email(
        $recipient,
        $subject,
        $message,
        $from = MAIL['FROM'],
        $from_name = MAIL['NAME'],
        $reply_to = '',
        $reply_to_name = '',
        $cc = '',
        $bcc = '',
        $attachments = ''
    ) {
        $debug = MAIL['DEBUG'] ?? 0;

        // Always log that email() was invoked so we can confirm execution.
        @file_put_contents(__DIR__ . '/../../email-called.log', '[' . date('c') . "] called: {$recipient} subject={$subject} debug={$debug}\n", FILE_APPEND | LOCK_EX);

        // Ensure consistent types for cc/bcc/attachments
        $ccArray = is_array($cc) ? $cc : ($cc !== '' ? [$cc] : []);
        $bccArray = is_array($bcc) ? $bcc : ($bcc !== '' ? [$bcc] : []);
        $attachmentsArray = is_array($attachments) ? $attachments : ($attachments !== '' ? [$attachments] : []);

        $logEntry = '[' . date('c') . "] To: {$recipient} Subject: {$subject}\n{$message}\n";

        // Render HTML email template (fallback to plain text if not needed)
        $htmlMessage = email_render_template(
            $subject,
            '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        );

        // Try using PHPMailer (SMTP) if available
        $mailerLoaded = false;
        $mailerError = null;

        if (file_exists(DIR . 'resources/includes/phpmailer/src/PHPMailer.php')) {
            require_once DIR . 'resources/includes/phpmailer/src/PHPMailer.php';
            require_once DIR . 'resources/includes/phpmailer/src/SMTP.php';
            require_once DIR . 'resources/includes/phpmailer/src/Exception.php';

            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = MAIL['HOST'] ?? '';
                $mail->SMTPAuth = true;
                $mail->Username = MAIL['USER'] ?? '';
                $mail->Password = MAIL['PASS'] ?? '';
                $mail->SMTPSecure = MAIL['SECURE'] ?? '';
                $mail->Port = (int) (MAIL['PORT'] ?? 587);
                $mail->CharSet = 'UTF-8';

                if ($debug) {
                    $mail->SMTPDebug = 0;
                    if ($debug >= 2) {
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = function ($str) use (&$logEntry) {
                            $logEntry .= $str;
                        };
                    }
                }

                // Ensure the envelope sender matches the authenticated SMTP user, to prevent
                // SendAsDenied/DMARC failures for custom From domains.
                $envelopeFrom = MAIL['USER'] ?? $from;
                $mail->setFrom($envelopeFrom, $from_name);
                $mail->Sender = $envelopeFrom; // envelope-from (used for SMTP MAIL FROM)
                $mail->addAddress($recipient);

                // If the desired From address differs from the authenticated SMTP account,
                // set it as Reply-To so replies go to the expected address.
                if ($from !== $envelopeFrom && $from !== '') {
                    $mail->addReplyTo($from, $from_name);

                    // Log this mismatch so we can troubleshoot custom domain sending.
                    $warning = sprintf(
                        "[%s] INFO: SMTP user '%s' differs from From '%s' - using Reply-To.\n",
                        date('c'),
                        $envelopeFrom,
                        $from
                    );
                    @file_put_contents(DIR . 'mail.log', $warning, FILE_APPEND | LOCK_EX);
                    @file_put_contents(DIR . 'resources/mail.log', $warning, FILE_APPEND | LOCK_EX);
                }

                if ($reply_to !== '') {
                    $mail->addReplyTo($reply_to, $reply_to_name);
                }

                // Optional DKIM signing when configured
                if (!empty(MAIL['DKIM_DOMAIN']) && !empty(MAIL['DKIM_SELECTOR']) && !empty(MAIL['DKIM_PRIVATE_KEY'])) {
                    $mail->DKIM_domain = MAIL['DKIM_DOMAIN'];
                    $mail->DKIM_selector = MAIL['DKIM_SELECTOR'];
                    $mail->DKIM_private = MAIL['DKIM_PRIVATE_KEY'];
                    if (!empty(MAIL['DKIM_PASS'])) {
                        $mail->DKIM_passphrase = MAIL['DKIM_PASS'];
                    }
                }

                foreach ($ccArray as $c) {
                    if ($c) {
                        $mail->addCC($c);
                    }
                }

                foreach ($bccArray as $b) {
                    if ($b) {
                        $mail->addBCC($b);
                    }
                }

                foreach ($attachmentsArray as $a) {
                    if ($a) {
                        $mail->addAttachment($a);
                    }
                }

                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $htmlMessage;
                $mail->AltBody = strip_tags($message);

                // SSL verification settings (useful for shared hosting/mismatched TLS names)
                if (empty(MAIL['VERIFY_SSL'])) {
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                }

                // Always attempt to send, even when debug output is enabled.
                // Debug mode 2 will still log SMTP conversation (if available).
                $mailerLoaded = $mail->send();
            } catch (Exception $e) {
                $mailerError = $e->getMessage();
            }
        }

        // Always log when debug is enabled.
        // Write to both the project root and a known-writable directory.
        if ($debug) {
            @file_put_contents(DIR . 'mail.log', $logEntry, FILE_APPEND | LOCK_EX);
            @file_put_contents(DIR . 'resources/mail.log', $logEntry, FILE_APPEND | LOCK_EX);
        }

        if ($mailerLoaded === true) {
            return true;
        }

        // Fall back to PHP mail() if PHPMailer not available or send failed.
        if ($mailerError) {
            $logEntry .= "PHPMailer error: {$mailerError}\n";
        }

        $headers = [];
        if ($from !== '') {
            $headers[] = 'From: ' . (trim($from_name) ? sprintf('%s <%s>', $from_name, $from) : $from);
        }
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . (trim($reply_to_name) ? sprintf('%s <%s>', $reply_to_name, $reply_to) : $reply_to);
        }
        if (!empty($ccArray)) {
            $headers[] = 'Cc: ' . implode(', ', $ccArray);
        }
        if (!empty($bccArray)) {
            $headers[] = 'Bcc: ' . implode(', ', $bccArray);
        }
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $envelopeFrom = MAIL['USER'] ?? $from;
        $additionalParams = '';
        if ($envelopeFrom) {
            $additionalParams = '-f' . escapeshellarg($envelopeFrom);
        }

        $sent = mail($recipient, $subject, $message, implode("\r\n", $headers), $additionalParams);
        if ($sent === true) {
            return true;
        }

        $error = error_get_last();
        return $mailerError ?: ($error['message'] ?? 'Unknown mail error');
    }

    /**
     * Simple rate limiting stored in session.
     *
     * @param string $key       Unique key per endpoint (e.g. "api_auth").
     * @param int    $limit     Maximum number of requests.
     * @param int    $windowSec Time window in seconds.
     *
     * @return array{allowed:bool, remaining:int, reset:int}
     */
    function mol_rate_limit(string $key, int $limit, int $windowSec): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $now = time();
        $entry = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'start' => $now];

        if (!isset($entry['count'], $entry['start']) || !is_int($entry['count']) || !is_int($entry['start'])) {
            $entry = ['count' => 0, 'start' => $now];
        }

        if ($now - $entry['start'] >= $windowSec) {
            $entry = ['count' => 0, 'start' => $now];
        }

        $entry['count'] += 1;
        $_SESSION['rate_limit'][$key] = $entry;

        $remaining = max(0, $limit - $entry['count']);
        $reset = $entry['start'] + $windowSec;

        return [
            'allowed' => $entry['count'] <= $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }

    /**
     * Authentication helpers (legacy `mol_` function names used in dashboard).
     */
    function is_user_logged_in(): bool
    {
        return !empty($_SESSION['user_id']) && is_int($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    function mol_signon(array $credentials)
    {
        global $link;

        $auth = new Auth($link);
        $user = $auth->login(
            (string) ($credentials['user_login'] ?? ''),
            (string) ($credentials['user_password'] ?? ''),
            !empty($credentials['remember'])
        );

        return $user;
    }

    function mol_logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Minimal helper to read JSON request body.
     */
    function mol_get_json_body(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
