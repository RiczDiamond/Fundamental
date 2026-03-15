<?php

// Simple AJAX auth endpoint (login/logout/me) for the JavaScript UI.
// Lives in resources/ajax and is intended to be called via jQuery.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$auth = new Auth($link);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $user = $auth->current_user();
    unset($user['user_pass']);

    echo json_encode(['user' => $user]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!mol_require_valid_nonce('global_csrf')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = strtolower(trim((string) ($input['action'] ?? '')));

if ($action === 'logout') {
    $auth->logout();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'login') {
    $login = (string) ($input['user_login'] ?? '');
    $password = (string) ($input['user_password'] ?? '');
    $remember = !empty($input['remember']);

    if ($auth->login($login, $password, $remember)) {
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['error' => 'Ongeldige gebruikersnaam of wachtwoord']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing or unsupported action']);
