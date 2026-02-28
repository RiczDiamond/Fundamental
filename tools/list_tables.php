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

$stmt = $link->query("SHOW TABLES");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
if (empty($rows)) {
    echo "No tables in database.\n";
    exit(0);
}
foreach ($rows as $r) {
    echo $r[0] . "\n";
}
