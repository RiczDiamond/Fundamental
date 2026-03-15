<?php
require_once __DIR__ . '/resources/php/init.php';

$rows = get_results('SELECT id, user_login, user_email FROM ' . table('users') . ' LIMIT 10');
var_export($rows);
