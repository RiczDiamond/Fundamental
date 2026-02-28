<?php

    // ----------------------
    // PROJECT SETTINGS
    // ----------------------
    define('PROJECT', [
        'NAME' => 'PROJECT_NAME',
        'AUTHOR' => 'AUTHOR_NAME',
        'ENVIRONMENT' => 'development', // of 'production'
        'SLOGAN' => 'Project slogan here',
        'KEYWORDS' => 'comma, separated, keywords',
        'DESCRIPTION' => 'Short project description',
        'URL' => 'https://example.com/',
        'URL_BLOG' => 'https://blog.example.com/',
        'URL_TOOLS' => 'https://tools.example.com/',
        'URL_FORUM' => 'https://forum.example.com/',
        'URL_VAULT' => 'https://vault.example.com/',
        'URL_GAMES' => 'https://games.example.com/',
        'URL_SERVERS' => 'https://servers.example.com/',
        'URL_API' => 'https://api.example.com/',
        'URL_ADMIN' => 'https://admin.example.com/',
        'URL_ACCOUNT' => 'https://account.example.com/',
        'URL_RESOURCES' => 'https://resources.example.com/',
    ]);

    // ----------------------
    // DIRECTORY PATHS
    // ----------------------
    // Project root directory
    define('DIR', realpath(__DIR__ . '/../../../') . '/');
    define('DIR_WEBSITE', realpath(DIR . '_website') . '/');
    define('DIR_BLOG', realpath(DIR . '_blog') . '/');
    define('DIR_TOOLS', realpath(DIR . '_tools') . '/');
    define('DIR_FORUM', realpath(DIR . '_forum') . '/');
    define('DIR_VAULT', realpath(DIR . '_vault') . '/');
    define('DIR_GAMES', realpath(DIR . '_games') . '/');
    define('DIR_API', realpath(DIR . '_api') . '/');
    define('DIR_SERVERS', realpath(DIR . '_servers') . '/');
    define('DIR_ADMIN', realpath(DIR . '_admin') . '/');
    define('DIR_ACCOUNT', realpath(DIR . '_account') . '/');
    define('DIR_RESOURCES', realpath(DIR . 'resources') . '/');

    // ----------------------
    // SOCIAL LINKS
    // ----------------------
    define('SOCIAL', [
        'DISCORD' => 'https://discord.gg/your-code',
        'INSTAGRAM' => 'https://www.instagram.com/your-handle',
        'X' => 'https://x.com/your-handle',
    ]);

    // ----------------------
    // SERVER SETTINGS
    // ----------------------
    define('SERVER_SETTINGS', [
        'CACHE_CONTROL' => 'no-cache, must-revalidate',
        'CONTENT_TYPE' => 'text/html',
        'CHARSET' => 'UTF-8',
        'MEMORY_LIMIT' => '1024M',
        'TIMEZONE' => 'Europe/Amsterdam',
    ]);

    // ----------------------
    // MAIL SETTINGS
    // ----------------------
    define('MAIL', [
        'FROM' => 'noreply@example.com',
        'NAME' => 'Project Mailer',
        'HOST' => 'smtp.example.com',
        'PORT' => 587, // TLS
        'USER' => 'smtp-user@example.com',
        'PASS' => 'yourStrongPassword!123'
    ]);

    // ----------------------
    // AUTHENTICATION SETTINGS
    // ----------------------
    define('AUTHENTICATION', [
        'DIFFICULTY' => 'medium',
        'LENGTH' => 12,
        'ALGORITHM' => PASSWORD_BCRYPT,
        'PEPPER' => [
            'ALGORITHM' => 'sha512',
            'VALUE' => '7NQEG-q5~#y5V?/x2-_wH1s,@?AIr[|9.3!Vgu?SqC+X-sm+-&lS6481<&[.>V,'
        ],
        'COST' => 10
    ]);

    // ----------------------
    // DATABASE SETTINGS
    // ----------------------
    define('DB', [
        'HOST' => 'localhost',
        'NAME' => 'fundamental',
        'USER' => 'root',
        'PASS' => '',
        'PREFIX' => '', // optioneel, bijv. 'mw_'
        'CHARSET' => 'utf8mb4' // veiliger dan UTF8
    ]);

    // ----------------------
    // ENVIRONMENT SETTINGS
    // ----------------------
    header('Cache-Control: ' . SERVER_SETTINGS['CACHE_CONTROL']);
    header('Content-Type: ' . SERVER_SETTINGS['CONTENT_TYPE'] . '; charset=' . SERVER_SETTINGS['CHARSET']);

    ini_set('memory_limit', SERVER_SETTINGS['MEMORY_LIMIT']);
    ini_set('session.gc_maxlifetime', 3600); // 1 uur
    ini_set('session.cookie_secure', 1); // Alleen via HTTPS
    ini_set('session.cookie_httponly', 1); // Geen JS toegang
    ini_set('session.use_strict_mode', 1); // Strikte sessies

    date_default_timezone_set(SERVER_SETTINGS['TIMEZONE']);

    // require_once $_SERVER['DOCUMENT_ROOT'] . '/resources/php/config_functions.php';
    // require_once $_SERVER['DOCUMENT_ROOT'] . '/resources/php/config.php';

    // require_once DIR . '/resources/php/init.php';
    // require_once DIR . '/resources/php/email.php';

    // if (isset($_POST) && !empty($_POST)) {

    //     $error;
    //     $required = [
    //         'name' => 'Er is geen naam ingevuld.',
    //         'email' => 'Er is geen emailadres ingevuld.'
    //     ];

    //     if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {

    //         $error['email'] = 'Het ingevulde emailadres is ongeldig.';

    //     }