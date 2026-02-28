<?php
// Simple seeder runner: executes resources/sql/seed.sql via PDO
// Usage: php tools/seed.php

declare(strict_types=1);

chdir(__DIR__ . '/../');
require_once __DIR__ . '/../bootstrap.php';

global $link;

if (!isset($link) || !($link instanceof PDO)) {
    $dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
    if (file_exists($dbHelper)) {
        require_once $dbHelper;
        if (!isset($link) && isset($GLOBALS['link']) && $GLOBALS['link'] instanceof PDO) {
            $link = $GLOBALS['link'];
        }
    }

    if (!isset($link) || !($link instanceof PDO)) {
        echo "No database connection available.\n";
        exit(1);
    }
}

$sqlFile = __DIR__ . '/../resources/sql/seed.sql';
if (!file_exists($sqlFile)) {
    echo "Seed file not found: {$sqlFile}\n";
    exit(1);
}

$contents = file_get_contents($sqlFile);
// remove -- comments
$lines = explode("\n", $contents);
$clean = [];
foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || strpos($trim, '--') === 0) continue;
    $clean[] = $line;
}

$contents = implode("\n", $clean);

// Split statements by semicolon followed by newline (best-effort)
$parts = preg_split('/;\s*\n/', $contents);

foreach ($parts as $idx => $stmt) {
    $s = trim($stmt);
    if ($s === '') continue;
    echo "Executing statement #" . ($idx + 1) . "...\n";
    try {
        $link->exec($s);
    } catch (Exception $e) {
        echo "Failed at statement #" . ($idx + 1) . ": " . $e->getMessage() . "\n";
        echo "Statement: " . substr($s, 0, 300) . "\n";
        exit(1);
    }
}

echo "Seed executed successfully.\n";
