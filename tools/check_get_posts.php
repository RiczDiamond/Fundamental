<?php
require __DIR__ . '/../bootstrap.php';
// ensure DB link is available (bootstrap may not create it)
global $link;
if (!isset($link) || !($link instanceof PDO)) {
    $dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
    if (file_exists($dbHelper)) {
        require_once $dbHelper;
        if (!isset($link) && isset($GLOBALS['link']) && $GLOBALS['link'] instanceof PDO) {
            $link = $GLOBALS['link'];
        }
    }
}

if (!function_exists('get_posts')) {
    require __DIR__ . '/../resources/php/helpers/templates.php';
}

$posts = get_posts(10);
echo 'count: ' . count($posts) . PHP_EOL;
if (!empty($posts)) {
    var_export(array_map(function($p){ return $p['ID'] ?? $p['id'] ?? null; }, $posts));
}
