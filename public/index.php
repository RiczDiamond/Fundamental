<?php

    require_once '../core/bootstrap.php';

    $account    = new Account($link);
    $auth       = new Auth($link);
    $cookie     = new Cookie();
    $session    = new Session($link);
    $logger     = new Logging($link);

    echo "<pre>";
    print_r($_SESSION);
    // // print_r($_SERVER);
    // // print_r($_GET);
    // // print_r($params);
    // print_r($url);
    echo "</pre>";

    if (!$session->has('csrf')) {
        $session->set('csrf', bin2hex(random_bytes(32)));
    }
    $csrfToken = $session->get('csrf');

    $logger->handleRequest($_SESSION['user_id'] ?? null);


     if ( isset($url[0]) && $url[0] === 'login' ) {

        require_once '../views/login.view.php';

    }

    
     if ( isset($url[0]) && $url[0] === 'logout' ) {

        require_once '../views/logout.view.php';

    }

    if ( isset($url[0]) && $url[0] === 'register' ) {

        require_once '../views/register.view.php';

    }

    if ( isset($url[0]) && $url[0] === 'dashboard' ) {

        require_once '../views/dashboard.view.php';

    }
