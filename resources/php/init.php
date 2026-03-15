<?php

    declare(strict_types=1);

    // Load configuration (including .env) before URL/host detection.
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/url.php';

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/slug.php';
    require_once __DIR__ . '/email_template.php';

    // Security headers (CSP/etc. are best added at the webserver, but we add a safe baseline here).
    mol_send_security_headers();

    // Global CSRF protection (applies to POST/PUT/PATCH/DELETE).
    mol_csrf_protect();

    // Automatically inject CSRF fields into HTML <form method="post"> tags.
    // This makes it safe by default without needing to add `mol_csrf_field()` everywhere.
    ob_start('mol_auto_csrf_in_forms');

    // Core domain classes
    require_once __DIR__ . '/class_account.php';
    require_once __DIR__ . '/class_auth.php';

    // Register default post types (similar to WordPress) so the rest of the app can rely on them.
    if (function_exists('mol_register_post_type')) {
        mol_register_post_type('post', [
            'labels' => ['singular' => 'Bericht', 'plural' => 'Berichten'],
        ]);
        mol_register_post_type('page', [
            'labels' => ['singular' => 'Pagina', 'plural' => 'Pagina\'s'],
            'has_archive' => false,
        ]);
    }
