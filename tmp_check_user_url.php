<?php
require __DIR__ . '/resources/php/init.php';

// Update user_url to ensure the column is writable.
$link->exec("UPDATE users SET user_url = 'https://example.com' WHERE id = 1");

$stmt = $link->prepare('SELECT id, user_url FROM users WHERE id = 1');
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
