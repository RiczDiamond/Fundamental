<?php

// List all users (for admin dashboard).
// Requires authentication.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$account = new Account($link);

// Support status updates via PUT/PATCH/POST
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
        if (!mol_require_valid_nonce('global_csrf')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

    $action = $input['action'] ?? '';
    $userId = (int) ($input['id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user id']);
        exit;
    }

    if ($action === 'ban' || $action === 'unban' || $action === 'soft_delete') {
        // Only users with manage_users capability can modify other users
        if (!mol_current_user_can('manage_users')) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        if ($action === 'ban') {
            $ok = $account->set_user_status($userId, 1);
        } elseif ($action === 'unban') {
            $ok = $account->set_user_status($userId, 0);
        } else {
            // soft delete: mark as deleted (2) and optionally clear sensitive fields
            $ok = $account->set_user_status($userId, 2);
        }

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }
}

// Default: list
$users = $account->get_all();

// Hide passwords and sensitive fields
foreach ($users as &$user) {
    unset($user['user_pass']);
}

echo json_encode(['users' => $users]);
