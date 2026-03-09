<?php

    require __DIR__ . '/../partials/header.php';

    if (!isset($page) || empty($page)) {
        http_response_code(404);
        echo '<h1>Page not found</h1>';
    } else {
        $sections = get_page_sections($link, (int) $page['ID']);

        echo '<article>';
        // echo '<h1>' . esc_html((string) $page['post_title']) . '</h1>';

        if (!empty($sections)) {
            render_flexible_sections($sections);
        } else {
            echo (string) $page['post_content'];
        }

        echo '</article>';
    }

    require __DIR__ . '/../partials/footer.php';
