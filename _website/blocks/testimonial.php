<?php

$quote = (string) ($section['fields']['quote'] ?? '"Sterk resultaat en prettig samenwerken."');
$author = (string) ($section['fields']['author'] ?? 'Tevreden klant');
$role = (string) ($section['fields']['role'] ?? '');

component_section_open('section-testimonial');
echo '<blockquote>' . component_escape_html($quote) . '</blockquote>';

    if ($author !== '' || $role !== '') {
        echo '<p>';

        if ($author !== '') {
            echo '<strong>' . component_escape_html($author) . '</strong>';
        }

        if ($role !== '') {
            echo ' - ' . component_escape_html($role);
        }

        echo '</p>';
    }

component_section_close();
