<?php

    if ( !defined('BASE_URL') ) {
        die('Direct access not allowed');
    }

    /**
     * Simple frontend router for posts/pages.
     */
    $slug = trim((string) ($url[0] ?? ''));
    $sub = trim((string) ($url[1] ?? ''));

    $template = 'index';
    $context = [];

    if ($slug === '') {
        $template = 'front-page';

    } elseif (in_array($slug, ['category', 'tag'], true) && $sub !== '') {
        // Taxonomy archive: /category/{slug} or /tag/{slug}
        $taxonomy = $slug;
        $term = get_row(
            'SELECT t.term_id, t.name, t.slug, tt.taxonomy
             FROM ' . table('terms') . ' t
             JOIN ' . table('term_taxonomy') . ' tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = :taxonomy AND t.slug = :slug
             LIMIT 1',
            ['taxonomy' => $taxonomy, 'slug' => $sub]
        );

        if (!$term) {
            http_response_code(404);
            $template = '404';
        } else {
            $template = $taxonomy === 'category' ? 'category' : 'tag';
            $context['term'] = $term;
            $context['posts'] = get_results(
                'SELECT p.* FROM ' . table('posts') . ' p
                 JOIN ' . table('term_relationships') . ' tr ON tr.object_id = p.ID
                 JOIN ' . table('term_taxonomy') . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tt.taxonomy = :taxonomy AND tt.term_id = :term_id
                 ORDER BY p.post_date DESC',
                ['taxonomy' => $taxonomy, 'term_id' => $term['term_id']]
            );
        }

    } elseif (mol_is_post_type($slug)) {
        // /{post_type} (archive) or /{post_type}/{slug} (single)
        $postType = $slug;
        if ($sub !== '') {
            $template = 'single-' . $postType;
            $context['post'] = mol_get_post_by_slug($sub, $postType);
            if (!$context['post']) {
                http_response_code(404);
                $template = '404';
            } else {
                $context['post']['meta'] = mol_get_post_meta((int) $context['post']['ID']);
                $context['post']['terms'] = mol_get_post_terms((int) $context['post']['ID']);
            }
        } else {
            $template = 'archive-' . $postType;
            $context['posts'] = mol_get_posts(['post_type' => $postType, 'status' => 'publish', 'limit' => 20]);
        }

    } else {
        // Try a page by slug
        $template = 'page';
        $context['post'] = mol_get_post_by_slug($slug, 'page');
        if (!$context['post']) {
            http_response_code(404);
            $template = '404';
        }
    }

    // Fallback templates
    $templateFile = __DIR__ . '/templates/' . $template . '.php';
    if (!is_file($templateFile)) {
        // fallback to generic templates
        if (str_starts_with($template, 'single-')) {
            $templateFile = __DIR__ . '/templates/single.php';
        } elseif (str_starts_with($template, 'archive-')) {
            $templateFile = __DIR__ . '/templates/archive.php';
        } else {
            $templateFile = __DIR__ . '/templates/index.php';
        }
    }

    // Expose theme variables.
    $styleVersion = '1';

    require_once __DIR__ . '/templates/_header.php';
    require_once $templateFile;
    require_once __DIR__ . '/templates/_footer.php';
