<?php


    require_once __DIR__ . '/resources/php/init.php';

    

    // echo "<pre>";
    // print_r([
    //     'BASE_URL' => BASE_URL,
    //     'SUBDOMAIN' => SUBDOMAIN,
    //     'URL' => $url,
    //     'MAIL' => MAIL],
        
    //     );
    // echo "</pre>";

    if (isset($url[0]) && $url[0] === 'api') {

        require_once './_api/_setup.php';

    } elseif (isset($url[0]) && in_array($url[0], ['dashboard', 'login', 'wachtwoord-vergeten'], true)) {

        require_once './_dashboard/_setup.php';

    } else {

        require_once './_website/_setup.php';

    }