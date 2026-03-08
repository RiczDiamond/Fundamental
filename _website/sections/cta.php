<?php

    $title = (string) ($section['fields']['title'] ?? 'Call to action');
    $buttonLabel = (string) ($section['fields']['button_label'] ?? 'Lees meer');
    $buttonUrl = (string) ($section['fields']['button_url'] ?? '/');

    echo '<section class="section-cta">';
    echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    echo '<a href="' . htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</section>';
