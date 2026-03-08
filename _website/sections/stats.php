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

    echo '<section class="section-stats">';

    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }

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
            echo '<strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong>';
        }

        if ($label !== '') {
            echo '<p>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        echo '</article>';
    }

    echo '</div>';
    echo '</section>';
