<?php

    declare(strict_types=1);

    // Load configuration (including .env) before URL/host detection.
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/url.php';

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/email_template.php';

    // Core domain classes
    require_once __DIR__ . '/class_account.php';
    require_once __DIR__ . '/class_auth.php';