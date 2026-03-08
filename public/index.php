<?php

    require_once '../helpers/url.php';
    require_once '../helpers/params.php';
    require_once '../helpers/config.php';
    require_once '../helpers/database.php';
    require_once '../helpers/functions.php';


    echo "<pre>";
    print_r([
        'BASE_URL' => BASE_URL,
        'SUBDOMAIN' => SUBDOMAIN,
        'URL' => $url,
        'PARAMS' => $params,
    ]);
    echo "</pre>";

    if ( isset($url[0]) && $url[0] === 'dashboard' ) {

        echo "<h1>Welcome to the Dashboard</h1>";

    } else {

        require_once '../_website/_setup.php';

    }