<?php
$require = require __DIR__ . '/../bootstrap.php';

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

$stmt = $link->query("SHOW TABLES LIKE 'wp_%'");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
if (empty($rows)) {
    echo "No wp_ tables found.\n";
    exit(0);
}
foreach ($rows as $t) {
    echo $t . "\n";
}
