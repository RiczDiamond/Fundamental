<?php

    // load Composer autoloader first; this provides HTMLPurifier, PHPMailer, etc.
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    require_once '../resources/php/Helpers/url.php';
    require_once '../resources/php/Helpers/params.php';
    require_once '../resources/php/Helpers/config.php';
    require_once '../resources/php/Helpers/database.php';
    require_once '../resources/php/Helpers/functions.php';

    require_once '../resources/php/Classes/Session.php';
    require_once '../resources/php/Classes/Cookie.php';
    require_once '../resources/php/Classes/Account.php';
    require_once '../resources/php/Classes/Auth.php';

    if (!function_exists('mol_auth_bootstrap')) {
        
        function mol_auth_bootstrap(PDO $link): void {
            static $authInstance = null;

            if ($authInstance === null) {
                $session = new Session();
                $cookies = new Cookie();
                $accountRepo = new Account($link);

                $authInstance = new Auth($session, $cookies, $accountRepo);
                $GLOBALS['_mol_auth_instance'] = $authInstance;
            }

            $authInstance->bootstrap_auth();
        }
    }

    if (!function_exists('mol_get_auth_instance')) {
        function mol_get_auth_instance(): ?Auth {
            $instance = $GLOBALS['_mol_auth_instance'] ?? null;
            return $instance instanceof Auth ? $instance : null;
        }
    }

    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool {
            $auth = mol_get_auth_instance();
            return $auth ? $auth->is_user_logged_in() : false;
        }
    }

    if (!function_exists('mol_get_current_user')) {
        function mol_get_current_user(): ?array {
            $auth = mol_get_auth_instance();
            return $auth ? $auth->get_current_user() : null;
        }
    }

    if (!function_exists('mol_get_current_user_display_name')) {
        function mol_get_current_user_display_name(): string {
            $user = mol_get_current_user();
            if (!is_array($user)) {
                return '';
            }

            return trim((string) ($user['display_name'] ?? $user['user_login'] ?? ''));
        }
    }

    if (!function_exists('mol_signon')) {
        function mol_signon(array $credentials): array|false {
            global $link;

            if (!isset($link) || !$link instanceof PDO) {
                return false;
            }

            $userLogin = trim((string) ($credentials['user_login'] ?? ''));
            $userPassword = (string) ($credentials['user_password'] ?? '');
            $remember = !empty($credentials['remember']);

            if ($userLogin === '' || $userPassword === '') {
                return false;
            }

            $repo = new Account($link);
            $user = $repo->get_user_by_login($userLogin);

            if (!$user || !password_verify($userPassword, (string) ($user['user_pass'] ?? ''))) {
                return false;
            }

            $auth = mol_get_auth_instance();
            if (!$auth) {
                mol_auth_bootstrap($link);
                $auth = mol_get_auth_instance();
            }

            if (!$auth) {
                return false;
            }

            // Normalize user array: always provide 'id' (numeric)
            if (isset($user['ID']) && !isset($user['id'])) {
                $user['id'] = (int) $user['ID'];
            }
            $auth->set_auth_cookie($user, $remember);
            return $user;
        }
    }

    if (!function_exists('mol_logout')) {
        function mol_logout(): void {
            $auth = mol_get_auth_instance();
            if ($auth) {
                $auth->logout();
            }
        }
    }

    // serve existing static files directly (js/css/images etc.)
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $staticPath = realpath(__DIR__ . $requestUri);
    if ($staticPath !== false && is_file($staticPath) && str_starts_with($staticPath, realpath(__DIR__))) {
        $mime = mime_content_type($staticPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000');
        readfile($staticPath);
        exit;
    }

    mol_auth_bootstrap($link);

    // Lightweight request debug logging to help diagnose redirect/cookie issues.
    try {
        $logDir = __DIR__ . '/../var/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/auth_debug.log';
        $entry = [
            'time' => date('c'),
            'url' => ($_GET['url'] ?? ''),
            'request_uri' => ($_SERVER['REQUEST_URI'] ?? ''),
            'host' => ($_SERVER['HTTP_HOST'] ?? ''),
            'session_status' => session_status(),
            'session_id' => session_id(),
            'is_user_logged_in' => function_exists('is_user_logged_in') ? (is_user_logged_in() ? true : false) : null,
            'session' => $_SESSION ?? [],
            'cookies' => $_COOKIE,
        ];

        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // ignore logging failures
    }

    // echo "<pre>";
    // print_r([
    //     'BASE_URL' => BASE_URL,
    //     'SUBDOMAIN' => SUBDOMAIN,
    //     'URL' => $url,
    //     'PARAMS' => $params,
    // ]);
    // echo "</pre>";

    if (isset($url[0]) && $url[0] === 'api') {

        require_once '../api/_setup.php';

    } elseif (isset($url[0]) && in_array($url[0], ['dashboard', 'login', 'wachtwoord-vergeten'], true)) {

        require_once '../_dashboard/_setup.php';

    } else {

        require_once '../_website/_setup.php';

        // $password = 'test';
        // $hash = password_hash($password, PASSWORD_DEFAULT);
        // echo $hash; // plak dit in je SQL insert

    }