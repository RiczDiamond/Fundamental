<?php
    // Example dedicated view for the "page" post type.
    // This loads the generic posts overview but forces the current post type to "page".

    $postType = 'page';
    require_once __DIR__ . '/posts.php';
