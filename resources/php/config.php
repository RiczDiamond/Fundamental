<?php

    define('DIR', realpath(__DIR__ . '/../../') . '/');

    if (!function_exists('cfg_load_env_file')) {
        /**
         * Minimal .env loader for local development.
         */
        function cfg_load_env_file(string $envFilePath): void {
            if (!is_file($envFilePath) || !is_readable($envFilePath)) {
                return;
            }

            $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                return;
            }

            foreach ($lines as $line) {
                $line = trim((string) $line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $name = trim($parts[0]);
                $value = trim($parts[1]);

                if ($name === '' || preg_match('/^[A-Z0-9_]+$/', $name) !== 1) {
                    continue;
                }

                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Always allow .env to override existing environment vars for local/dev.
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    // `.env` lives in the project root. The helpers directory is at
    // resources/php, so climb two levels up to get the root.
    // Earlier code pointed at resources/php/.env which doesn't exist,
    // meaning MAIL/IMAP constants were always using default placeholders.
    cfg_load_env_file(dirname(__DIR__, 2) . '/.env');

    if (!function_exists('cfg_env')) {
        function cfg_env(string $key, ?string $default = null): ?string {
            $value = getenv($key);

            if ($value === false) {
                return $default;
            }

            $value = trim((string) $value);
            return $value !== '' ? $value : $default;
        }
    }

    if (!function_exists('cfg_env_int')) {
        function cfg_env_int(string $key, int $default): int {
            $value = cfg_env($key, null);

            if ($value === null || !is_numeric($value)) {
                return $default;
            }

            return (int) $value;
        }
    }

    if (!function_exists('cfg_env_bool')) {
        function cfg_env_bool(string $key, bool $default): bool {
            $value = cfg_env($key, null);

            if ($value === null) {
                return $default;
            }

            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
    }

    define('MAIL', [
        'FROM' => cfg_env('MAIL_FROM', 'noreply@molwebsites.nl'),
        'NAME' => cfg_env('MAIL_NAME', 'r.mol@molwebsites.nl'),
        'HOST' => cfg_env('MAIL_HOST', 'smtp.office365.com'),
        'PORT' => cfg_env_int('MAIL_PORT', 587),
        'USER' => cfg_env('MAIL_USER', 'r.mol@molwebsites.nl'),
        'PASS' => cfg_env('MAIL_PASS', ''),
        'SECURE' => cfg_env('MAIL_SECURE', 'tls'),
        'DEBUG' => cfg_env_int('MAIL_DEBUG', 0),
        // Whether to verify the SMTP server certificate (true by default).
        // Set to 0 for hosts with mismatched certificates (e.g. shared hosting TLS names).
        'VERIFY_SSL' => cfg_env_bool('MAIL_VERIFY_SSL', true),
        // Optional DKIM settings - only used if configured.
        'DKIM_DOMAIN' => cfg_env('MAIL_DKIM_DOMAIN', ''),
        'DKIM_SELECTOR' => cfg_env('MAIL_DKIM_SELECTOR', ''),
        'DKIM_PRIVATE_KEY' => cfg_env('MAIL_DKIM_PRIVATE_KEY', ''),
        'DKIM_PASS' => cfg_env('MAIL_DKIM_PASS', ''),
    ]);

    define('IMAP', [
        'ENABLED' => cfg_env_bool('IMAP_ENABLED', true),
        'HOST' => cfg_env('IMAP_HOST', 'outlook.office365.com'),
        'PORT' => cfg_env_int('IMAP_PORT', 993),
        'FLAGS' => cfg_env('IMAP_FLAGS', '/imap/ssl'),
        'MAILBOX' => cfg_env('IMAP_MAILBOX', 'INBOX'),
        'USER' => cfg_env('IMAP_USER', MAIL['USER']),
        'PASS' => cfg_env('IMAP_PASS', MAIL['PASS']),
        'LOOKBACK_DAYS' => cfg_env_int('IMAP_LOOKBACK_DAYS', 14),
        'MAX_FETCH' => cfg_env_int('IMAP_MAX_FETCH', 50),
    ]);

    define('AUTHENTICATION', [
        'DIFFICULTY' => cfg_env('AUTH_DIFFICULTY', 'medium'),
        'LENGTH' => cfg_env_int('AUTH_LENGTH', 12),
        'ALGORITHM' => PASSWORD_BCRYPT,
        'PEPPER' => [
            'ALGORITHM' => cfg_env('AUTH_PEPPER_ALGORITHM', 'sha512'),
            'VALUE' => cfg_env('AUTH_PEPPER_VALUE', '7NQEG-q5~#y5V?/x2-_wH1s,@?AIr[|9.3!Vgu?SqC+X-sm+-&lS6481<&[.>V,'),
        ],
        'COST' => cfg_env_int('AUTH_COST', 10),
    ]);

    define('DB', [
        'HOST' => cfg_env('DB_HOST', 'localhost'),
        'NAME' => cfg_env('DB_NAME', 'fundamental'),
        'USER' => cfg_env('DB_USER', 'root'),
        'PASS' => cfg_env('DB_PASS', ''),
        'PREFIX' => cfg_env('DB_PREFIX', ''),
        'CHARSET' => cfg_env('DB_CHARSET', 'utf8mb4'),
    ]);

    /**
     * Basisinstellingen voor PHP-sessies en sessie-cookie.
     *
     * Deze instellingen gelden voor alle plekken waar `session_start()` wordt aangeroepen
     * (nonce-helpers, authenticatie, etc.) en zorgen voor veilige cookie-flags.
     */
    if (PHP_SAPI !== 'cli') {
        // Detect HTTPS in common hosting / reverse proxy setups.
        // Allows `FORCE_HTTPS=1` for local dev or when HTTPS is terminated upstream.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || cfg_env_bool('FORCE_HTTPS', false);

        // Harden session cookie handling.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');

        if (PHP_VERSION_ID >= 70300) {
            ini_set('session.cookie_samesite', 'Lax');
        }

        if (!headers_sent()) {
            session_name('FUNDAMENTALSESSID');
        }
    }