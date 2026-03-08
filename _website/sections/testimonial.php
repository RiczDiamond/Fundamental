<?php

    $quote = (string) ($section['fields']['quote'] ?? '"Sterk resultaat en prettig samenwerken."');
    $author = (string) ($section['fields']['author'] ?? 'Tevreden klant');
    $role = (string) ($section['fields']['role'] ?? '');

    echo '<section class="section-testimonial">';
    echo '<blockquote>' . htmlspecialchars($quote, ENT_QUOTES, 'UTF-8') . '</blockquote>';

    if ($author !== '' || $role !== '') {
        echo '<p>';

        if ($author !== '') {
            echo '<strong>' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</strong>';
        }

        if ($role !== '') {
            echo ' - ' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        }

        echo '</p>';
    }

    echo '</section>';
