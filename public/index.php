<?php

    require_once '../core/bootstrap.php';

    $account    = new Account($link);
    $auth       = new Auth($link);
    $cookie     = new Cookie();
    $session    = new Session($link);
    $logger     = new Logging($link);

    // ...existing code...

    if (!$session->has('csrf')) {
        $session->set('csrf', bin2hex(random_bytes(32)));
    }
    $csrfToken = $session->get('csrf');

    $logger->handleRequest($_SESSION['user_id'] ?? null);

    if (isset($url[0]) && $url[0] === 'login') {
        require_once '../views/login.view.php';

    } elseif (isset($url[0]) && $url[0] === 'logout') {
        require_once '../views/logout.view.php';

    } elseif (isset($url[0]) && $url[0] === 'register') {
        require_once '../views/register.view.php';

    } elseif (isset($url[0]) && $url[0] === 'dashboard') {
        if (!$session->has('user_id')) {
            header('Location: /login');
            exit;
        }

        $allowedDashboardPages = ['overview', 'users', 'blogs', 'pages', 'profile'];
        $dashboardPage = $url[1] ?? 'overview';

        if (!in_array($dashboardPage, $allowedDashboardPages, true)) {
            http_response_code(404);
            echo '404 - Dashboard pagina niet gevonden';
            exit;
        }

        require_once '../views/dashboard.view.php';

    } else {
        http_response_code(404);
        echo '404 - Pagina niet gevonden';
    }