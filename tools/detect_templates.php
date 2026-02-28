<?php
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../resources/php/helpers/templates.php';

// ensure DB link for helpers that may query
$dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
if (file_exists($dbHelper)) require_once $dbHelper;

$cases = [
    [],
    ['home'],
    ['welcome-to-seed'],
    ['category', 'uncategorized'],
    ['author', 'admin'],
    ['search', 'seed']
];

foreach ($cases as $c) {
    $t = detect_template_from_url($c);
    echo "URL segments: " . json_encode($c) . PHP_EOL;
    echo " -> template: " . ($t['template'] ?? 'null') . PHP_EOL;
    echo " -> context keys: " . json_encode(array_keys($t['context'] ?? [])) . PHP_EOL;
    echo PHP_EOL;
}
