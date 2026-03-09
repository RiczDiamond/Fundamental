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

component_section_open('section-features');
component_heading($title, 'h3');
component_paragraph($intro);

    echo '<ul>';

    foreach ($items as $item) {
        $label = trim((string) $item);

        if ($label === '') {
            continue;
        }

        echo '<li>' . component_escape_html($label) . '</li>';
    }

    echo '</ul>';
    component_section_close();
