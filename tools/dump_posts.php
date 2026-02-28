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
if (!isset($link)) { echo "No DB link\n"; exit(1); }

try {
    $stmt = $link->query('SELECT * FROM posts LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Rows: " . count($rows) . "\n";
    foreach ($rows as $r) {
        foreach ($r as $k => $v) {
            echo "$k: ";
            if (is_null($v)) echo "NULL"; else echo substr($v,0,200);
            echo "\n";
        }
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
