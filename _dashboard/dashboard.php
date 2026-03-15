<?php

    // Dashboard router / view loader.
    // This file is included via _dashboard/_setup.php after login check.

    // Determine section from URL: /dashboard/{section}[/...]
    $section = $url[1] ?? 'home';
    $postType = '';

    // Allow URLs like /dashboard/posts/{post_type}
    if ($section === 'posts') {
        $postType = trim((string) ($url[2] ?? ''));
    }

    $allowed = ['home', 'account', 'posts', 'users', 'roles', 'audit', 'verify-email'];

    if (!in_array($section, $allowed, true)) {
        http_response_code(404);
        echo '<h1>Pagina niet gevonden</h1>';
        exit;
    }

    require_once __DIR__ . '/views/header.php';

    // If a dedicated view exists for this post type, use it.
    if ($section === 'posts' && $postType !== '') {
        $customView = __DIR__ . '/views/posts-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $postType) . '.php';
        if (is_file($customView)) {
            require_once $customView;
            require_once __DIR__ . '/views/footer.php';
            return;
        }
    }

    $viewFile = __DIR__ . '/views/' . $section . '.php';

    if (is_file($viewFile)) {
        require_once $viewFile;
    } else {
        echo '<div style="padding:24px;">View niet gevonden: ' . esc_attr($section) . '</div>';
    }

    require_once __DIR__ . '/views/footer.php';

    require_once __DIR__ . '/views/footer.php';
