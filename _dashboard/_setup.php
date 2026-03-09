<?php

    if ( !defined('BASE_URL') ) {
        die('Direct access not allowed');
    }

    if ( isset($url[0]) && $url[0] === 'login') {
        require_once __DIR__ . '/login.php';
        return;
    }

    if ( isset($url[0]) && $url[0] === 'dashboard' ) {

        if (isset($url[1]) && $url[1] === 'logout') {
        
            mol_logout();
            mol_safe_redirect('/login');
        
        }

        require_once __DIR__ . '/dashboard.php';
        return;
    }

    mol_safe_redirect('/login');