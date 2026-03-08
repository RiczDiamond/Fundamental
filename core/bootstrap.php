<?php

    $projectRoot = dirname(__DIR__);
    $vendorAutoload = $projectRoot . '/vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;

        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        }
    }

    session_start();

    require_once '../core/helpers.php';

    // Config
    require_once '../core/config.php';

    // Database
    require_once '../core/database.php';
    
    // Helpers

    // Legacy global classes under /classes are loaded via Composer classmap.


