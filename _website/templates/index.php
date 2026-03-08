<?php

    require __DIR__ . '/../partials/header.php';

    if (isset($page) && is_array($page) && !empty($page)) {
        echo '<article>';
        echo '<h1>' . htmlspecialchars((string) $page['post_title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        echo (string) $page['post_content'];
        echo '</article>';
    } elseif (!empty($posts)) {
        echo '<h1>Archief</h1>';
        echo '<ul>';
        foreach ($posts as $postItem) {
            $slug = rawurlencode((string) ($postItem['post_name'] ?? ''));
            $title = htmlspecialchars((string) ($postItem['post_title'] ?? 'Zonder titel'), ENT_QUOTES, 'UTF-8');
            echo '<li><a href="/?url=' . $slug . '">' . $title . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<h1>Geen content gevonden</h1>';
    }

    require __DIR__ . '/../partials/footer.php';
