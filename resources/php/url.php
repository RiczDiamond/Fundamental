<?php

    // ---------- SCHEME ----------
    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = (!empty($https) && strtolower($https) !== 'off') ? 'https' : 'http';

    // ---------- HOST ----------
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-z0-9\.\-:\[\]]/i', '', $host);

    if ($host === '') {
        $host = 'localhost';
    }

    define('BASE_URL', $scheme . '://' . $host);

    // ---------- SUBDOMAIN ----------
    $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
    $hostWithoutPort = trim($hostWithoutPort, '[]');

    $parts = explode('.', strtolower($hostWithoutPort));

    $subdomain = '';

    if (count($parts) > 2) {
        $subdomain = ($parts[0] === 'www' && isset($parts[1])) ? $parts[1] : $parts[0];
    }

    define('SUBDOMAIN', $subdomain);


    // ---------- URL PARSE ----------
    $raw = trim($_GET['url'] ?? '', "/ \t\r\n");

    $segments = array_values(array_filter(
        array_map(
            fn($s) => preg_replace('/[^a-z0-9\-]/', '', strtolower($s)),
            preg_split('#/+#', $raw)
        )
    ));

    // ---------- DEFAULT ----------
    $controller = $segments[0] ?? 'home';
    $action     = $segments[1] ?? 'index';
    $params     = array_slice($segments, 2);


    // ---------- RESULT ----------
    $url = [
        'controller' => $controller,
        'action'     => $action,
        'params'     => $params
    ];

    // Legacy numeric access (used in index.php routing)
    foreach ($segments as $i => $segment) {
        $url[$i] = $segment;
    }
