<?php

    if ( !defined('BASE_URL') ) {
        die('Direct access not allowed');
    }

    $requestedSlug = $url[0] ?? 'home';
    $templatesDir = __DIR__ . '/templates';
    $templateCandidates = [];
    $page = null;
    $posts = [];

    if ($requestedSlug === 'archive') {
        $archiveType = (string) get_query_var('type', 'page');
        $limit = (int) get_query_var('limit', 20);
        $limit = max(1, min(100, $limit));

        $posts = get_posts($link, $archiveType, $limit);

        $templateCandidates[] = 'archive.php';
        $templateCandidates[] = 'index.php';
    } else {
        if ($requestedSlug === 'home' && isset($_GET['url']) && trim((string) $_GET['url']) !== '') {
            mol_safe_redirect(get_permalink_by_slug('home'), 301);
        }

        $resolvedPage = resolve_post_by_slug($link, $requestedSlug, 'page');
        $resolvedType = 'page';

        if (!$resolvedPage) {
            $resolvedPage = resolve_post_by_slug($link, $requestedSlug, 'post');
            $resolvedType = 'post';
        }

        if (!$resolvedPage) {
            http_response_code(404);
            $templateCandidates[] = '404.php';
            $templateCandidates[] = 'index.php';
        } else {
            $page = $resolvedPage['post'];

            if (!empty($resolvedPage['matched_old_slug'])) {
                $target = get_post_permalink($page);
                mol_safe_redirect($target, 301);
            }

            $safeSlug = preg_replace('/[^a-z0-9\-]/i', '', (string) $page['post_name']);

            if ($resolvedType === 'page' && $safeSlug === 'home') {
                $templateCandidates[] = 'front-page.php';
            }

            if ($resolvedType === 'post') {
                $templateCandidates[] = 'single-' . $safeSlug . '.php';
                $templateCandidates[] = 'single.php';
            }

            $templateCandidates[] = 'page-' . $safeSlug . '.php';
            $templateCandidates[] = 'page.php';
            $templateCandidates[] = 'index.php';
        }
    }

    foreach ($templateCandidates as $templateFile) {
        $templatePath = $templatesDir . '/' . $templateFile;

        if (is_file($templatePath)) {
            require $templatePath;
            return;
        }
    }

    http_response_code(500);
    echo '<h1>Template missing</h1>';