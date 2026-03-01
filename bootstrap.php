<?php

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . DIRECTORY_SEPARATOR);
}

require_once APP_ROOT . 'resources/php/config/config.php';
require_once APP_ROOT . 'resources/php/helpers/database.php';

if (!isset($pdo) && isset($link) && $link instanceof PDO) {
    $pdo = $link;
}

foreach (glob(APP_ROOT . 'resources/php/helpers/*.php') as $helperFile) {
    if (basename($helperFile) === 'database.php') {
        continue;
    }

    require_once $helperFile;
}

require_once APP_ROOT . 'resources/php/classes/class_cookie.php';
require_once APP_ROOT . 'resources/php/classes/class_session.php';
require_once APP_ROOT . 'resources/php/classes/class_auth.php';
require_once APP_ROOT . 'resources/php/classes/class_rate_limiter.php';

foreach (glob(APP_ROOT . 'resources/php/classes/class_*.php') as $classFile) {
    require_once $classFile;
}

if (!defined('SITE_NAME')) {
    $projectName = PROJECT['NAME'] ?? 'Fundamental';
    define('SITE_NAME', $projectName);
}

if (!defined('COOKIE_ENCRYPTION_KEY')) {
    $envCookieKey = getenv('COOKIE_ENCRYPTION_KEY');

    if (!empty($envCookieKey)) {
        define('COOKIE_ENCRYPTION_KEY', $envCookieKey);
    } else {
        $fallbackSeed = AUTHENTICATION['PEPPER']['VALUE'] ?? PROJECT['NAME'] ?? 'fundamental-cookie-key';
        define('COOKIE_ENCRYPTION_KEY', hash('sha256', (string) $fallbackSeed));
    }
}
