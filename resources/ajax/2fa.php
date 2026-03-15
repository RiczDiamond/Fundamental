<?php

// AJAX endpoint for basic 2FA setup/management.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = strtolower(trim((string) ($input['action'] ?? '')));

$user = $auth->current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user']);
    exit;
}

if (!mol_require_valid_nonce('global_csrf', $input)) {
    // Debug helper: log CSRF state when validation fails.
    $postedAction = $_POST['_nonce_action'] ?? $_SERVER['HTTP_X_CSRF_ACTION'] ?? '';
    $postedNonce = $_POST['_nonce'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $storedNonce = $_SESSION['nonces']['global_csrf'] ?? null;

    $debug = sprintf(
        "%s - 2FA CSRF fail: postedAction=%s postedNonce=%s stored=%s\n",
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

if ($action === 'generate') {
    // Generate (pending) secret for 2FA setup.
    $secret = $auth->generate_two_factor_secret($userId);

    // Build an otpauth URL for QR code generation.
    $issuer = urlencode(parse_url(BASE_URL, PHP_URL_HOST) ?: 'Site');
    $label = urlencode($user['user_email'] ?? $user['user_login'] ?? 'user');
    $otpauth = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

    echo json_encode(['secret' => $secret, 'otpauth' => $otpauth]);
    exit;
}

if ($action === 'verify') {
    $code = trim((string) ($input['code'] ?? ''));
    if ($code === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing code']);
        exit;
    }

    if (!$auth->confirm_two_factor_secret($userId, $code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige 2FA-code']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'recovery_codes') {
    if (!$auth->account->is_two_factor_enabled($userId)) {
        http_response_code(400);
        echo json_encode(['error' => '2FA is niet ingeschakeld']);
        exit;
    }

    $codes = $auth->generate_two_factor_recovery_codes($userId);
    echo json_encode(['codes' => $codes]);
    exit;
}

if ($action === 'clear_recovery_codes') {
    if (!$auth->account->is_two_factor_enabled($userId)) {
        http_response_code(400);
        echo json_encode(['error' => '2FA is niet ingeschakeld']);
        exit;
    }

    $auth->clear_two_factor_recovery_codes($userId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'disable') {
    $auth->disable_two_factor($userId);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing or unsupported action']);
