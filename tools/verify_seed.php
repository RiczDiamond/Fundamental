<?php
require __DIR__ . '/../bootstrap.php';

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

if (!isset($link) || !($link instanceof PDO)) {
    echo "No DB connection.\n";
    exit(1);
}

$checks = [
    'users' => 'SELECT COUNT(*) FROM users',
    'posts' => 'SELECT COUNT(*) FROM posts',
    'terms' => 'SELECT COUNT(*) FROM terms',
    'options' => 'SELECT COUNT(*) FROM options',
    'comments' => 'SELECT COUNT(*) FROM comments',
];

foreach ($checks as $k => $sql) {
    try {
        $count = (int) $link->query($sql)->fetchColumn();
        echo $k . ': ' . $count . "\n";
    } catch (Exception $e) {
        echo $k . ': ERROR - ' . $e->getMessage() . "\n";
    }
}
