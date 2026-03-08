<?php

    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'fundamental';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS');
    if ($dbPass === false) {
        $dbPass = '';
    }

    $siteName = getenv('SITE_NAME') ?: 'Fundamental CMS';
    $siteUrl = getenv('SITE_URL') ?: 'http://localhost/fundamental/';

    define('DB', [
        'HOST' => $dbHost,
        'NAME' => $dbName,
        'USER' => $dbUser,
        'PASS' => $dbPass,
    ]);

    // Site instellingen
    $site = [
        'name' => $siteName,
        'url' => $siteUrl,
    ];