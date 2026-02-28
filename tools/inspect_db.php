<?php
// Inspect database schema for troubleshooting
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
}

if (!isset($link) || !($link instanceof PDO)) {
    echo "No DB connection available.\n";
    exit(1);
}

try {
    echo "DESCRIBE posts:\n";
    $stmt = $link->query('DESCRIBE posts');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\t" . $c['Null'] . "\t" . $c['Key'] . "\t" . ($c['Default'] ?? 'NULL') . "\t" . ($c['Extra'] ?? '') . "\n";
    }
} catch (Exception $e) {
    echo "Error inspecting posts: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "\nSample row from posts (LIMIT 1):\n";
    $r = $link->query('SELECT * FROM posts LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        foreach ($r as $k => $v) {
            echo $k . ': ' . (is_null($v) ? 'NULL' : $v) . "\n";
        }
    } else {
        echo "No rows in posts table.\n";
    }
} catch (Exception $e) {
    echo "Error fetching sample row: " . $e->getMessage() . "\n";
}

try {
    echo "\nAuthors table sample:\n";
    $stmt = $link->query('SELECT id, name, slug FROM authors LIMIT 10');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($rows) {
        foreach ($rows as $r) {
            echo $r['id'] . "\t" . $r['slug'] . "\t" . $r['name'] . "\n";
        }
    } else {
        echo "No authors found.\n";
    }
} catch (Exception $e) {
    echo "Error fetching authors: " . $e->getMessage() . "\n";
}

try {
    echo "\nApplied migrations:\n";
    $stmt = $link->query('SELECT id, name, batch, migrated_at FROM migrations ORDER BY id ASC');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $r) {
        echo $r['id'] . "\t" . $r['name'] . "\tbatch:" . $r['batch'] . "\t" . $r['migrated_at'] . "\n";
    }
} catch (Exception $e) {
    echo "Error fetching migrations: " . $e->getMessage() . "\n";
}
