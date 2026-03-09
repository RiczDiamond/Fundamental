<?php

    if ( !defined('BASE_URL') ) {
        die('Direct access not allowed');
    }

    if ( isset($url[0]) && $url[0] === 'login') {
        require_once __DIR__ . '/login.php';
        return;
    }

    if ( isset($url[0]) && $url[0] === 'dashboard' ) {
        require_once __DIR__ . '/dashboard.php';
        return;
    }

    wp_safe_redirect('/login');