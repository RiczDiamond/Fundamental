<?php

    ini_set("display_errors", 1);
    ini_set("display_startup_errors", 1);
    error_reporting(E_ALL);

    require_once __DIR__ . "/../bootstrap.php";

    // Ensure $url is defined (helpers/url.php normally sets it)
    if (!isset($url) || !is_array($url)) {
        $url = ['home'];
    }

    //! Router
    if (isset($url[0]) && $url[0] == 'dashboard') {

        require_once DIR . '/_dashboard/_setup.php';

    } else {

        if (isset($url[0]) && $url[0] === 'api') {

            require_once DIR_API . '/_setup.php';
            exit;

        }
        
        // Frontend
        require_once DIR . '/_website/_setup.php';

    }