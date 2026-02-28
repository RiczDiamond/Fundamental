<?php
// Simple migration runner for plain PHP projects
// Usage: php tools/migrate.php [--down] [--all]

declare(strict_types=1);

chdir(__DIR__ . '/../');

require_once __DIR__ . '/../bootstrap.php';

global $link;

// If bootstrap didn't establish $link, try to require the database helper directly
if (!isset($link) || !($link instanceof PDO)) {
    $dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
    if (file_exists($dbHelper)) {
        require_once $dbHelper;
        // database.php should set $link in the global scope; try to fetch from $GLOBALS
        if (!isset($link) && isset($GLOBALS['link']) && $GLOBALS['link'] instanceof PDO) {
            $link = $GLOBALS['link'];
        }
    }

    if (!isset($link) || !($link instanceof PDO)) {
        echo "No database connection available (\$link)\n";
        exit(1);
    }
}

$down = in_array('--down', $argv, true);
$all = in_array('--all', $argv, true);

// ensure migrations table
$link->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// fetch applied migrations
$stmt = $link->query('SELECT name FROM migrations ORDER BY id ASC');
$applied = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];

$migFiles = glob(__DIR__ . '/../resources/migrations/*.php');
sort($migFiles, SORT_STRING);

if ($down) {
    // rollback: if --all drop all applied in reverse
    $toRollback = $all ? array_reverse($applied) : (count($applied) ? [end($applied)] : []);
    if (empty($toRollback)) {
        echo "Nothing to rollback.\n";
        exit(0);
    }

    foreach ($toRollback as $name) {
        $path = __DIR__ . '/../resources/migrations/' . $name . '.php';
        if (!file_exists($path)) {
            echo "Migration file for {$name} not found, skipping.\n";
            // remove record anyway
            $link->prepare('DELETE FROM migrations WHERE name = :name')->execute([':name' => $name]);
            continue;
        }
        $mig = include $path;
        if (is_array($mig) && isset($mig['down']) && is_callable($mig['down'])) {
            echo "Rolling back: {$name}\n";
            try {
                // Let the migration handle its own transactions. Simply call down() and remove record on success.
                $mig['down']($link);
                $link->prepare('DELETE FROM migrations WHERE name = :name')->execute([':name' => $name]);
            } catch (Exception $e) {
                if ($link->inTransaction()) {
                    $link->rollBack();
                }
                echo "Failed rollback {$name}: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
    }

    echo "Rollback complete.\n";
    exit(0);
}

// Apply pending migrations
$pending = [];
foreach ($migFiles as $file) {
    $base = basename($file, '.php');
    if (!in_array($base, $applied, true)) {
        $pending[] = ['name' => $base, 'path' => $file];
    }
}

if (empty($pending)) {
    echo "No pending migrations.\n";
    exit(0);
}

$batchStmt = $link->query('SELECT MAX(batch) FROM migrations');
$maxBatch = $batchStmt ? (int)$batchStmt->fetchColumn() : 0;
$batch = $maxBatch + 1;

foreach ($pending as $m) {
    echo "Applying migration: {$m['name']}...\n";
    $mig = include $m['path'];
    if (!is_array($mig) || !isset($mig['up']) || !is_callable($mig['up'])) {
        echo "Invalid migration format for {$m['name']}, skipping.\n";
        continue;
    }

    try {
        // Let the migration manage transactions itself.
        $mig['up']($link);
        $stmt = $link->prepare('INSERT INTO migrations (name, batch) VALUES (:name, :batch)');
        $stmt->execute([':name' => $m['name'], ':batch' => $batch]);
        echo "Applied {$m['name']}.\n";
    } catch (Exception $e) {
        try {
            if ($link->inTransaction()) {
                $link->rollBack();
            }
        } catch (Exception $rb) {
            // ignore rollback errors
        }
        echo "Failed to apply {$m['name']}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migrations complete.\n";
