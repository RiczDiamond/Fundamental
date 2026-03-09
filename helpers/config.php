<?php

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