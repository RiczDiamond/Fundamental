<?php

    require __DIR__ . '/../partials/header.php';

    echo '<h1>Archief</h1>';

    if (empty($posts)) {
        echo '<p>Geen resultaten.</p>';
    } else {
        echo '<ul>';
        foreach ($posts as $postItem) {
            $slug = rawurlencode((string) ($postItem['post_name'] ?? ''));
            $title = htmlspecialchars((string) ($postItem['post_title'] ?? 'Zonder titel'), ENT_QUOTES, 'UTF-8');
            echo '<li><a href="/?url=' . $slug . '">' . $title . '</a></li>';
        }
        echo '</ul>';
    }

    require __DIR__ . '/../partials/footer.php';
