<?php

// AJAX endpoint to manage role/capability configuration.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (!mol_current_user_can('edit_roles')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['roles' => mol_role_capabilities()]);
    exit;
}

if ($method === 'POST') {
    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!isset($input['roles']) || !is_array($input['roles'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    // Validate structure: role => array of string caps
    $clean = [];
    foreach ($input['roles'] as $role => $caps) {
        if (!is_string($role) || trim($role) === '') {
            continue;
        }
        if (!is_array($caps)) {
            continue;
        }
        $cleanCaps = [];
        foreach ($caps as $cap) {
            if (is_string($cap) && trim($cap) !== '') {
                $cleanCaps[] = trim($cap);
            }
        }
        $clean[trim($role)] = array_values(array_unique($cleanCaps));
    }

    if (!mol_set_roles_config($clean)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save configuration']);
        exit;
    }

    echo json_encode(['success' => true, 'roles' => $clean]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
