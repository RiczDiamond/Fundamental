<?php
require __DIR__ . '/../bootstrap.php';

global $link;
if (!isset($link)) {
    require_once __DIR__ . '/../resources/php/helpers/database.php';
}

try {
    $sql = 'SELECT * FROM posts WHERE post_type = :type ORDER BY post_date DESC LIMIT 10';
    $stmt = $link->prepare($sql);
    $stmt->execute([':type' => 'post']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Query rows: " . count($rows) . PHP_EOL;
    var_export(array_keys($rows[0] ?? []));
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage().PHP_EOL;
}
