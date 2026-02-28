<?php


    if (isset($url) && is_array($url) && $url[0] === 'dashboard') {

       

    } else {

        require_once DIR_DASHBOARD . '404.php';

    }

