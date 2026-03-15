<?php

/**
 * Account API endpoints (current logged-in user).
 *
 * Endpoints:
 *  - GET  /api/account      (current user data)
 *  - PUT  /api/account      (update current user)
 */

function handle_api_account(?string $id): void
{
    $auth = new Auth($GLOBALS['link']);

    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_api_account_get($auth);
            break;
        case 'PUT':
        case 'PATCH':
            handle_api_account_put($auth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_account_get(Auth $auth): void
{
    $user = $auth->current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    unset($user['user_pass']);
    echo json_encode(['user' => $user]);
}

function handle_api_account_put(Auth $auth): void
{
    $userId = $auth->id();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $input = mol_get_json_body();

    // CSRF check (global token)
    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $allowed = ['display_name', 'user_email', 'user_login', 'user_url'];

    $data = array_intersect_key($input, array_flip($allowed));

    $account = new Account($GLOBALS['link']);
    $currentUser = $account->get_user_by_id($userId);

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    // Validate email uniqueness
    if (!empty($data['user_email'])) {
        $other = $account->get_user_by_email($data['user_email']);
        if ($other && (int) ($other['id'] ?? 0) !== $userId) {
            http_response_code(409);
            echo json_encode(['error' => 'Dit e-mailadres is al in gebruik']);
            return;
        }
    }

    // Normalize and validate URL
    if (!empty($data['user_url'])) {
        $userUrl = trim((string) $data['user_url']);
        if ($userUrl !== '' && !preg_match('#^https?://#i', $userUrl)) {
            $userUrl = 'https://' . $userUrl;
        }
        if (!filter_var($userUrl, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige website-URL. Gebruik een volledige URL (inclusief http/https).']);
            return;
        }
        $data['user_url'] = $userUrl;
    }

    // Validate login uniqueness
    if (!empty($data['user_login'])) {
        $other = $account->get_user_by_login($data['user_login']);
        if ($other && (int) ($other['id'] ?? 0) !== $userId) {
            http_response_code(409);
            echo json_encode(['error' => 'Deze gebruikersnaam is al in gebruik']);
            return;
        }
    }

    // Password update (requires current password)
    if (!empty($input['current_password']) && !empty($input['new_password'])) {
        if (!$auth->verify_password((string) $input['current_password'], (string) ($currentUser['user_pass'] ?? ''))) {
            http_response_code(401);
            echo json_encode(['error' => 'Huidig wachtwoord klopt niet']);
            return;
        }
        $data['user_pass'] = $auth->hash_password((string) $input['new_password']);
    } elseif (!empty($input['current_password']) || !empty($input['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Voor het wijzigen van het wachtwoord moet u huidig en nieuw wachtwoord invullen']);
        return;
    }

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided']);
        return;
    }

    $ok = $account->update_user($userId, $data);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
        return;
    }

    $user = $auth->current_user();
    unset($user['user_pass']);
    echo json_encode(['user' => $user]);
}
