<?php

// API endpoints used by the JavaScript frontend.
// Always return JSON.

header('Content-Type: application/json; charset=utf-8');

if (!is_user_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$action = $url[1] ?? '';

if ($action === 'save-page') {
    require_once __DIR__ . '/save-page.php';
    return;
}

http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Endpoint niet gevonden']);
