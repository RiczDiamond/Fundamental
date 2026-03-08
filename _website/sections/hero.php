<?php

    $headline = (string) ($section['fields']['headline'] ?? 'Hero titel');
    $subline = (string) ($section['fields']['subline'] ?? '');

    echo '<section class="section-hero">';
    echo '<h2>' . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . '</h2>';

    if ($subline !== '') {
        echo '<p>' . htmlspecialchars($subline, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    echo '</section>';
