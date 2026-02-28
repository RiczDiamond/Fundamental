<?php
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../resources/php/helpers/templates.php';

// ensure DB
$dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
if (file_exists($dbHelper)) require_once $dbHelper;

$posts = get_posts(10);
setup_loop($posts);

while (have_posts()) {
    the_post();
    echo "Title: "; the_title(); echo "\n";
    echo "Permalink: " . get_permalink() . "\n";
    echo "Excerpt: "; the_excerpt(); echo "\n---\n";
}

wp_reset_postdata();
