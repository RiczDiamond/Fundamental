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

if (!mol_require_valid_nonce('global_csrf', $input)) {
    // Debug helper: log CSRF state when validation fails.
    $postedAction = $_POST['_nonce_action'] ?? $_SERVER['HTTP_X_CSRF_ACTION'] ?? '';
    $postedNonce = $_POST['_nonce'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $storedNonce = $_SESSION['nonces']['global_csrf'] ?? null;

    $debug = sprintf(
        "%s - Auth CSRF fail: postedAction=%s postedNonce=%s stored=%s\n",
        date('c'),
        $postedAction,
        $postedNonce ? substr($postedNonce, 0, 8) : '(empty)',
        $storedNonce ? substr($storedNonce, 0, 8) : '(none)'
    );

    @file_put_contents(__DIR__ . '/csrf-debug.log', $debug, FILE_APPEND | LOCK_EX);

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
    $rateKey = 'auth_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate = mol_rate_limit($rateKey, 5, 60 * 5); // 5 attempts per 5 minutes
    if (!$rate['allowed']) {
        http_response_code(429);
        echo json_encode(['error' => 'Te veel inlogpogingen. Probeer het later opnieuw.']);
        exit;
    }

    $login = (string) ($input['user_login'] ?? '');
    $password = (string) ($input['user_password'] ?? '');
    $remember = !empty($input['remember']);

        $result = $auth->login($login, $password, $remember);
        if ($result === true) {
            mol_audit($auth->id() ?? 0, $auth->id() ?? null, 'login', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($result === 'locked') {
            http_response_code(429);
            echo json_encode(['error' => 'Account tijdelijk geblokkeerd vanwege te veel mislukte inlogpogingen. Probeer het over 15 minuten opnieuw.']);
            exit;
        }

        if ($result === 'ip_blocked') {
            http_response_code(429);
            echo json_encode(['error' => 'Uw IP-adres is geblokkeerd vanwege mislukte inlogpogingen. Probeer het later opnieuw.']);
            exit;
        }

        mol_audit(0, null, 'login_failed', ['login' => $login, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(401);
        echo json_encode(['error' => 'Ongeldige gebruikersnaam of wachtwoord']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Missing or unsupported action']);
    exit;

    http_response_code(400);
    echo json_encode(['error' => 'Missing or unsupported action']);
    exit;
