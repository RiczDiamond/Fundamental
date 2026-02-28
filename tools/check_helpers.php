<?php
require __DIR__ . '/../resources/php/helpers/templates.php';
$names = ['render_posts_section','render_post_item','render_single_post','render_page','esc'];
foreach ($names as $n) {
    echo $n . ': ' . (function_exists($n) ? 'yes' : 'no') . PHP_EOL;
}
