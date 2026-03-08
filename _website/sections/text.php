<?php

    $title = (string) ($section['fields']['title'] ?? '');
    $content = (string) ($section['fields']['content'] ?? '');

    echo '<section class="section-text">';

    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }

    if ($content !== '') {
        echo '<div>' . $content . '</div>';
    }

    echo '</section>';
