<?php

    declare(strict_types=1);

    // Alles automatisch inladen via autoloading
    spl_autoload_register(function ($class) {
        $file = __DIR__ . '/classes/class_' . strtolower($class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log("Autoloading failed: Class file for '$class' not found at '$file'");
        }
    });

    // ook de config automatisch inladen
    require_once __DIR__ . '/resources/php/config/config.php';

    // Load URL helper (defines $url) and other helpers as needed
    if (file_exists(__DIR__ . '/resources/php/helpers/url.php')) {
        require_once __DIR__ . '/resources/php/helpers/url.php';
    }

    // Load template helpers
    if (file_exists(__DIR__ . '/resources/php/helpers/templates.php')) {
        require_once __DIR__ . '/resources/php/helpers/templates.php';
    }
    
    // Load database helper to establish $link (PDO) for templates and helpers
    $dbHelper = __DIR__ . '/resources/php/helpers/database.php';
    if (file_exists($dbHelper)) {
        require_once $dbHelper;
        if (!isset($link) && isset($GLOBALS['link']) && $GLOBALS['link'] instanceof PDO) {
            $link = $GLOBALS['link'];
        }
    }
    