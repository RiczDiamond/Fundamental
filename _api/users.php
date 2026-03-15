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

    // Only users with the right capability can view/manage users.
    if (!mol_role_has_capability((string) ($current['user_role'] ?? ''), 'view_users')) {
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

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 25);
    $perPage = max(1, min(100, $perPage));

    $search = trim((string) ($_GET['q'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? ''));

    $status = null;
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status = (int) $_GET['status'];
        if (!in_array($status, [0, 1, 2], true)) {
            $status = null;
        }
    }

    $users = $account->get_users([
        'page' => $page,
        'per_page' => $perPage,
        'search' => $search,
        'role' => $role,
        'status' => $status,
    ]);

    foreach ($users as &$user) {
        unset($user['user_pass']);
    }
    unset($user);

    $total = $account->get_users_count([
        'search' => $search,
        'role' => $role,
        'status' => $status,
    ]);

    echo json_encode([
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ]);
}

function handle_api_users_create(): void
{
    $input = mol_get_json_body();

    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $current = (new Auth($GLOBALS['link']))->current_user();
    if (!mol_role_has_capability((string) ($current['user_role'] ?? ''), 'manage_users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $userLogin = trim((string) ($input['user_login'] ?? ''));
    $userEmail = trim((string) ($input['user_email'] ?? ''));
    $displayName = trim((string) ($input['display_name'] ?? ''));

    if ($userLogin === '' || $userEmail === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Admins should never set a user password directly.
    // Always generate a temporary password and send a reset link.
    $userPass = bin2hex(random_bytes(10));

    $auth = new Auth($GLOBALS['link']);
    $account = new Account($GLOBALS['link']);

    // Prevent duplicates
    if ($account->get_user_by_login($userLogin) || $account->get_user_by_email($userEmail)) {
        http_response_code(409);
        echo json_encode(['error' => 'User with that login or email already exists']);
        return;
    }

    $userRole = 'user';
    if (isset($input['user_role']) && in_array($input['user_role'], ['user', 'editor', 'admin'], true)) {
        $userRole = $input['user_role'];
    }

    $ok = $account->create_user([
        'user_login' => $userLogin,
        'user_email' => $userEmail,
        'user_pass' => $auth->hash_password($userPass),
        'display_name' => $displayName ?: $userLogin,
        'user_registered' => date('Y-m-d H:i:s'),
        'user_role' => $userRole,
    ]);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create user (maybe duplicate login/email)']);
        return;
    }

    // Send an initial invite/reset link to the newly created user.
    $newUser = $account->get_user_by_email($userEmail);
    if ($newUser) {
        send_user_invite_email($newUser);
        mol_audit_log((int) ($current['id'] ?? 0), (int) ($newUser['id'] ?? 0), 'user_created', [
            'role' => $userRole,
            'email' => $userEmail,
            'login' => $userLogin,
        ]);
    }

    echo json_encode(['success' => true]);
}

function send_user_invite_email(array $user): void
{
    // Generate reset token and store it.
    $selector = bin2hex(random_bytes(9));
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expires = time() + 3600;

    $payload = [
        'user_id' => (int) ($user['id'] ?? 0),
        'token_hash' => $tokenHash,
        'expires' => date('Y-m-d H:i:s', $expires),
    ];

    $account = new Account($GLOBALS['link']);
    $account->save_reset_token($selector, $payload);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetLink = $scheme . '://' . $host . '/wachtwoord-vergeten?selector=' . $selector . '&token=' . $rawToken;

    $subject = 'Uw account is aangemaakt';
    $body = "Hallo " . ($user['user_login'] ?? '') . ",\n\n";
    $body .= "Er is een account voor u aangemaakt. Klik op onderstaande link om uw wachtwoord in te stellen (1 uur geldig):\n" . $resetLink . "\n\n";
    $body .= "Als u dit niet heeft aangevraagd, kunt u deze e-mail negeren.\n\n";
    $body .= "Groeten,\nWebsite\n";

    $toEmail = $user['user_email'] ?? '';
    if ($toEmail !== '') {
        email(
            $toEmail,
            $subject,
            $body,
            MAIL['FROM'],
            MAIL['NAME'],
            '',
            '',
            [],
            [],
            []
        );
    }
}

function handle_api_users_update(): void
{
    $input = mol_get_json_body();

    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $current = (new Auth($GLOBALS['link']))->current_user();
    if (!mol_role_has_capability((string) ($current['user_role'] ?? ''), 'manage_users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
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
        // Prevent non-admins (and even admins) from banning themselves.
        $current = (new Auth($GLOBALS['link']))->current_user();
        if ((int) ($current['id'] ?? 0) === $userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot change own status']);
            return;
        }

        $status = 0;
        if ($action === 'ban') {
            $status = 1;
        } elseif ($action === 'soft_delete') {
            $status = 2;
        }
        $ok = $account->set_user_status($userId, $status);
        if ($ok) {
            $logAction = $action === 'ban' ? 'user_banned' : ($action === 'unban' ? 'user_unbanned' : 'user_deleted');
            mol_audit_log((int) ($current['id'] ?? 0), $userId, $logAction, ['status' => $status]);
        }
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
        if (isset($input['user_role']) && in_array($input['user_role'], ['user', 'editor', 'admin'], true)) {
            $data['user_role'] = $input['user_role'];
        }

        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            return;
        }

        // Prevent downgrading other admins
        $targetUser = $account->get_user_by_id($userId);
        $current = (new Auth($GLOBALS['link']))->current_user();
        if ($targetUser && ($targetUser['user_role'] ?? '') === 'admin' && ($current['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot modify admin user']);
            return;
        }

        $ok = $account->update_user($userId, $data);
        if ($ok) {
            mol_audit_log((int) ($current['id'] ?? 0), $userId, 'user_updated', ['fields' => array_keys($data)]);
        }
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
