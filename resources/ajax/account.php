<?php

// Simple AJAX endpoint for account operations.
// This mirrors the /api/account logic but lives in resources/ajax.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);

if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Allow JSON requests for PUT (since PHP doesn't populate $_PUT by default)
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $user = $auth->current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    unset($user['user_pass']);
    echo json_encode(['user' => $user]);
    exit;
}

if ($method === 'PUT' || $method === 'PATCH') {
if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = $auth->id();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $account = new Account($link);
    $currentUser = $account->get_user_by_id($userId);
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $allowed = ['display_name', 'user_email', 'user_login', 'user_url'];
    $data = array_intersect_key($input, array_flip($allowed));

    // Validate uniqueness
    if (!empty($data['user_email'])) {
        $other = $account->get_user_by_email($data['user_email']);
        if ($other && (int) ($other['id'] ?? 0) !== $userId) {
            http_response_code(409);
            echo json_encode(['error' => 'Dit e-mailadres is al in gebruik']);
            exit;
        }
    }

    if (!empty($data['user_login'])) {
        $other = $account->get_user_by_login($data['user_login']);
        if ($other && (int) ($other['id'] ?? 0) !== $userId) {
            http_response_code(409);
            echo json_encode(['error' => 'Deze gebruikersnaam is al in gebruik']);
            exit;
        }
    }

    if (!empty($input['current_password']) && !empty($input['new_password'])) {
        if (!$auth->verify_password((string) $input['current_password'], (string) ($currentUser['user_pass'] ?? ''))) {
            http_response_code(401);
            echo json_encode(['error' => 'Huidig wachtwoord klopt niet']);
            exit;
        }
        $data['user_pass'] = $auth->hash_password((string) $input['new_password']);
    } elseif (!empty($input['current_password']) || !empty($input['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Voor het wijzigen van het wachtwoord moet u huidig en nieuw wachtwoord invullen']);
        exit;
    }

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided']);
        exit;
    }

    $ok = $account->update_user($userId, $data);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
        exit;
    }

    $user = $auth->current_user();
    unset($user['user_pass']);
    echo json_encode(['user' => $user]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
