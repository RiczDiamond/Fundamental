<?php

    $title = (string) ($section['fields']['title'] ?? 'Features');
    $intro = (string) ($section['fields']['intro'] ?? '');
    $items = $section['fields']['items'] ?? [];

    if (is_string($items)) {
        $items = preg_split('/\r\n|\r|\n/', $items) ?: [];
    }

    if (!is_array($items) || $items === []) {
        $items = ['Snelle implementatie', 'Flexibele opbouw', 'Makkelijk te beheren'];
    }

    echo '<section class="section-features">';

    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }

    if ($intro !== '') {
        echo '<p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    echo '<ul>';

    foreach ($items as $item) {
        $label = trim((string) $item);

        if ($label === '') {
            continue;
        }

        echo '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    echo '</ul>';
    echo '</section>';
