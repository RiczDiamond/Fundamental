<?php

$cookieFile = __DIR__ . '/cookies.txt';

$ch = curl_init('http://fundamental.test/resources/ajax/auth.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-CSRF-Token: 00a08bef80144ea408645d23a76a28ba',
    'X-CSRF-Action: global_csrf',
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'action' => 'login',
    'user_login' => 'test',
    'user_password' => 'test',
]));

$res = curl_exec($ch);
$info = curl_getinfo($ch);

echo "HTTP/1.1 {$info['http_code']}\n";
echo $res;

curl_close($ch);
