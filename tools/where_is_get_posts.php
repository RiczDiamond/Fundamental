<?php
require __DIR__ . '/../bootstrap.php';

if (function_exists('get_posts')) {
    $r = new ReflectionFunction('get_posts');
    echo 'Defined in: ' . $r->getFileName() . ':' . $r->getStartLine() . PHP_EOL;
    echo "Source snippet:\n";
    $lines = file($r->getFileName());
    $start = $r->getStartLine() - 1;
    $end = min($r->getEndLine(), $start + 200) - 1;
    for ($i = $start; $i <= $end; $i++) echo ($i+1) . ': ' . $lines[$i];
} else {
    echo "get_posts not defined\n";
}
