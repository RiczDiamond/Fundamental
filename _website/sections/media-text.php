<?php

    $title = (string) ($section['fields']['title'] ?? 'Media met tekst');
    $content = (string) ($section['fields']['content'] ?? '');
    $image = (string) ($section['fields']['image'] ?? '');
    $buttonLabel = (string) ($section['fields']['button_label'] ?? '');
    $buttonUrl = (string) ($section['fields']['button_url'] ?? '');

    echo '<section class="section-media-text">';

    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }

    if ($image !== '') {
        echo '<p><img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></p>';
    }

    if ($content !== '') {
        echo '<div>' . $content . '</div>';
    }

    if ($buttonLabel !== '' && $buttonUrl !== '') {
        echo '<p><a href="' . htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }

    echo '</section>';
