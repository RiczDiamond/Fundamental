<?php

    require_once '../helpers/url.php';
    require_once '../helpers/params.php';
    require_once '../helpers/config.php';
    require_once '../helpers/database.php';
    require_once '../helpers/functions.php';


    // echo "<pre>";
    // print_r([
    //     'BASE_URL' => BASE_URL,
    //     'SUBDOMAIN' => SUBDOMAIN,
    //     'URL' => $url,
    //     'PARAMS' => $params,
    // ]);
    // echo "</pre>";

    if ( isset($url[0]) && $url[0] === 'api' ) {

        require_once '../api/_setup.php';

    } else if ( isset($url[0]) && $url[0] === 'dashboard' || isset($url[0]) && $url[0] === 'login' ) {

        require_once '../_dashboard/_setup.php';

    } else {

        require_once '../_website/_setup.php';

//         $password = 'test';
// $hash = password_hash($password, PASSWORD_DEFAULT);
// echo $hash; // plak dit in je SQL insert

    }