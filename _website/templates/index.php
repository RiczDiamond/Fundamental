<?php

    require __DIR__ . '/../partials/header.php';

    if (isset($page) && is_array($page) && !empty($page)) {
        echo '<article>';
        // echo '<h1>' . esc_html((string) $page['post_title']) . '</h1>';
        echo (string) $page['post_content'];
        echo '</article>';
    } elseif (!empty($posts)) {
        echo '<h1>Archief</h1>';
        echo '<ul>';
        foreach ($posts as $postItem) {
            $permalink = get_post_permalink($postItem);
            $title = esc_html((string) ($postItem['post_title'] ?? 'Zonder titel'));
            echo '<li><a href="' . esc_url($permalink) . '">' . $title . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<h1>Geen content gevonden</h1>';
    }

    require __DIR__ . '/../partials/footer.php';
