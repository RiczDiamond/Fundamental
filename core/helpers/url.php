<?php

    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

    $url_origin = isset($_SERVER['HTTP_HOST']) ? trim(strip_tags($_SERVER['HTTP_HOST'])) : '';
    $url_origin = filter_var($url_origin, FILTER_SANITIZE_URL);

    $host_parts = explode('.', $url_origin);

    define('SUBDOMAIN', count($host_parts) > 3 && strtolower($host_parts[0]) === 'www' ? $host_parts[1] : (count($host_parts) > 2 ? $host_parts[0] : ''));

    $url = isset($_GET['url']) && !empty($_GET['url']) ? explode('/', $_GET['url']) : ['home'];
    $last_index = count($url) - 1;

    if (empty($url[$last_index])) {

        unset($url[$last_index]);
        $url = array_values($url);
        
    }

    // if ( isset ($url[1]) ) {

    //     $title = ucwords(str_replace('-', ' ', $url[1])) . ' ' . ucwords(str_replace('-', ' ', $url[0]));

    // } else {

    //     $title = $url[0] !== 'home' ? ucwords(str_replace('-', ' ', $url[0])) : 'Home';

    // }

?>