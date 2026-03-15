<?php

/**
 * API endpoint for managing users.
 *
 * Endpoints:
 * - GET /api/users      => list all users (admin only)
 * - PATCH /api/users    => update user status (admin only)
 */

function handle_api_users(?string $id): void
{
    $auth = new Auth($GLOBALS['link']);

    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $current = $auth->current_user();
    if ((int) ($current['user_status'] ?? 0) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_api_users_get();
            break;
        case 'POST':
            handle_api_users_create();
            break;
        case 'PATCH':
        case 'PUT':
            handle_api_users_update();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_users_get(): void
{
    $account = new Account($GLOBALS['link']);
    $users = $account->get_all();

    foreach ($users as &$user) {
        unset($user['user_pass']);
    }

    echo json_encode(['users' => $users]);
}

function handle_api_users_create(): void
{
    $input = mol_get_json_body();

    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $userLogin = trim((string) ($input['user_login'] ?? ''));
    $userEmail = trim((string) ($input['user_email'] ?? ''));
    $userPass = trim((string) ($input['user_pass'] ?? ''));
    $displayName = trim((string) ($input['display_name'] ?? ''));

    if ($userLogin === '' || $userEmail === '' || $userPass === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $auth = new Auth($GLOBALS['link']);
    $account = new Account($GLOBALS['link']);

    $ok = $account->create_user([
        'user_login' => $userLogin,
        'user_email' => $userEmail,
        'user_pass' => $auth->hash_password($userPass),
        'display_name' => $displayName ?: $userLogin,
        'user_registered' => date('Y-m-d H:i:s'),
    ]);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create user (maybe duplicate login/email)']);
        return;
    }

    echo json_encode(['success' => true]);
}

function handle_api_users_update(): void
{
    $input = mol_get_json_body();

    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $action = $input['action'] ?? '';
    $userId = (int) ($input['id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user id']);
        return;
    }

    $account = new Account($GLOBALS['link']);

    if (in_array($action, ['ban', 'unban', 'soft_delete'], true)) {
        $status = 0;
        if ($action === 'ban') {
            $status = 1;
        } elseif ($action === 'soft_delete') {
            $status = 2;
        }
        $ok = $account->set_user_status($userId, $status);
    } elseif ($action === 'update') {
        $data = [];
        if (isset($input['display_name'])) {
            $data['display_name'] = trim((string) $input['display_name']);
        }
        if (isset($input['user_email'])) {
            $data['user_email'] = trim((string) $input['user_email']);
        }
        if (isset($input['user_login'])) {
            $data['user_login'] = trim((string) $input['user_login']);
        }

        if (isset($input['new_password']) && $input['new_password'] !== '') {
            $auth = new Auth($GLOBALS['link']);
            $data['user_pass'] = $auth->hash_password((string) $input['new_password']);
        }

        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            return;
        }

        $ok = $account->update_user($userId, $data);
    } elseif ($action === 'reset_password') {
        $newPassword = trim((string) ($input['new_password'] ?? ''));
        if ($newPassword === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing new password']);
            return;
        }

        $auth = new Auth($GLOBALS['link']);
        $ok = $account->update_user($userId, ['user_pass' => $auth->hash_password($newPassword)]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        return;
    }

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
        return;
    }

    echo json_encode(['success' => true]);
}
