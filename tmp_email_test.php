<?php
require __DIR__ . '/resources/php/init.php';

$m = MAIL;
var_dump($m);

$res = email('info@ricardomol.nl', 'Test', 'Test body', $m['FROM'], $m['NAME']);
var_dump($res);
