<?php
require_once __DIR__ . '/resources/php/init.php';

$link = $GLOBALS['link'];
$auth = new Auth($link);
$hash = $auth->hash_password('Password123!');

// Update user 'admin' password
$updated = db_query(
    'UPDATE ' . table('users') . ' SET user_pass = :hash WHERE user_login = :login',
    ['hash' => $hash, 'login' => 'admin']
);

echo "Updated: " . ($updated ? 'yes' : 'no') . "\n";
