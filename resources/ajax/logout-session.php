<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $auth->id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = trim((string) ($input['session_id'] ?? ''));

if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session id']);
    exit;
}

// Ensure user cannot logout current session via this endpoint
if ($sessionId === session_id()) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot logout current session']);
    exit;
}

$account = new Account($link);
$metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sessionId);
$account->delete_user_meta($userId, $metaKey);

echo json_encode(['success' => 'Sessie verwijderd']);
