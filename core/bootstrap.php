<?php

    // Classes are defined in the global namespace in /classes/*.php
    // Remove incorrect namespaced imports to avoid "class not found" errors

    session_start();

    require_once '../core/helpers.php';

    // Config
    require_once '../core/config.php';

    // Database
    require_once '../core/database.php';
    
    // Helpers

    // Classes
    require_once '../classes/Account.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Analytics.php';
    require_once '../classes/Cookie.php';
    require_once '../classes/Session.php';
    require_once '../classes/Logging.php';
    
