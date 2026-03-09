<?php

    require __DIR__ . '/../partials/header.php';

    echo '<h1>Archief</h1>';

    if (empty($posts)) {
        echo '<p>Geen resultaten.</p>';
    } else {
        echo '<ul>';
        foreach ($posts as $postItem) {
            $permalink = get_post_permalink($postItem);
            $title = esc_html((string) ($postItem['post_title'] ?? 'Zonder titel'));
            echo '<li><a href="' . esc_url($permalink) . '">' . $title . '</a></li>';
        }
        echo '</ul>';
    }

    require __DIR__ . '/../partials/footer.php';
