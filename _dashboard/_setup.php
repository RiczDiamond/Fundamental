<?php

    if ( !defined('BASE_URL') ) {
        die('Direct access not allowed');
    }

    if ( $url[0] === 'login') {

        require_once 'login.php';
    }

    if ( isset($url[0]) && $url[0] === 'dashboard' ) {
        
        require_once 'dashboard.php';

    }