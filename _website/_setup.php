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
        $archiveType = (string) getParam($params, 'type', 'page');
        $limit = (int) getParam($params, 'limit', 20);
        $limit = max(1, min(100, $limit));

        $posts = get_posts($link, $archiveType, $limit);

        $templateCandidates[] = 'archive.php';
        $templateCandidates[] = 'index.php';
    } else {
        $resolvedPage = resolve_post_by_slug($link, $requestedSlug, 'page');

        if (!$resolvedPage) {
            http_response_code(404);
            $templateCandidates[] = '404.php';
            $templateCandidates[] = 'index.php';
        } else {
            $page = $resolvedPage['post'];

            if (!empty($resolvedPage['matched_old_slug'])) {
                $target = BASE_URL . '/?url=' . rawurlencode((string) $page['post_name']);
                header('Location: ' . $target, true, 301);
                exit;
            }

            $safeSlug = preg_replace('/[^a-z0-9\-]/i', '', (string) $page['post_name']);

            if ($safeSlug === 'home') {
                $templateCandidates[] = 'front-page.php';
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