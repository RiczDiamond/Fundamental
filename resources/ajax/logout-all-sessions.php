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

// Remove all sessions except the current one.
$auth->logoutAllSessions($userId);

// Re-register current session so it remains active.
$auth->refreshSession();

echo json_encode(['success' => 'Uitgelogd op andere apparaten.']);
