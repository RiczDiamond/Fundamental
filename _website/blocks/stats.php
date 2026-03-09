<?php

$title = (string) ($section['fields']['title'] ?? 'Resultaten');
$items = $section['fields']['items'] ?? [];

    if (!is_array($items) || $items === []) {
        $items = [
            ['value' => '120+', 'label' => 'Projecten'],
            ['value' => '98%', 'label' => 'Tevreden klanten'],
            ['value' => '24h', 'label' => 'Gemiddelde reactietijd'],
        ];
    }

component_section_open('section-stats');
component_heading($title, 'h3');

    echo '<div class="stats-grid">';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $value = trim((string) ($item['value'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        if ($value === '' && $label === '') {
            continue;
        }

        echo '<article class="stat-item">';

        if ($value !== '') {
            echo '<strong>' . component_escape_html($value) . '</strong>';
        }

        if ($label !== '') {
            echo '<p>' . component_escape_html($label) . '</p>';
        }

        echo '</article>';
    }

    echo '</div>';
component_section_close();
