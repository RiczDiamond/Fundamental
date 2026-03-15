<?php

/**
 * Authentication API endpoints.
 *
 * Endpoints:
 *  - POST   /api/auth/login     { user_login, user_password, remember }
 *  - POST   /api/auth/logout
 *  - GET    /api/auth/me
 */

function handle_api_auth(?string $id): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $auth = new Auth($GLOBALS['link']);

    switch ($method) {
        case 'GET':
            handle_api_auth_me($auth);
            break;
        case 'POST':
            handle_api_auth_post($auth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_auth_me(Auth $auth): void
{
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $user = $auth->current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    unset($user['user_pass']);
    echo json_encode(['user' => $user]);
}

function handle_api_auth_post(Auth $auth): void
{
    $input = mol_get_json_body();

    // Rate limiting (per session) to prevent brute-force attempts.
    $rate = mol_rate_limit('api_auth', 6, 60); // 6 requests / 60s
    if (!$rate['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $rate['reset'] - time()));
        echo json_encode(['error' => 'Te veel verzoeken, probeer het over een moment opnieuw.']);
        return;
    }

    // CSRF protection: use a single global CSRF token.
    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($action === 'logout') {
        $auth->logout();
        echo json_encode(['success' => true]);
        return;
    }

    if ($action === 'login') {
        $login = (string) ($input['user_login'] ?? '');
        $password = (string) ($input['user_password'] ?? '');
        $remember = !empty($input['remember']);

        if ($auth->login($login, $password, $remember)) {
            echo json_encode(['success' => true]);
            return;
        }

        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Missing or unsupported action']);
}
