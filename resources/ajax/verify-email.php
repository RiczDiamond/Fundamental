<?php

// Endpoint to verify a pending email change.
// Expected query: ?selector=...&token=...

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$selector = $_GET['selector'] ?? '';
$token = $_GET['token'] ?? '';

if (!$selector || !$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige verificatiegegevens.']);
    exit;
}

$account = new Account($link);

$ok = $account->apply_pending_email_change($selector, $token);

if (!$ok) {
    http_response_code(400);
    echo json_encode(['error' => 'Verificatie mislukt. De link is mogelijk verlopen of onjuist.']);
    exit;
}

echo json_encode(['success' => 'Uw e-mailadres is geverifieerd en bijgewerkt.']);
