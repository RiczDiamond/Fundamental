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
$pendingEmailNotice = null;

if ($method === 'GET') {
    $user = $auth->current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $account = new Account($link);
    $sessions = $account->get_user_sessions((int) $user['id']);

    unset($user['user_pass']);

    $twoFactorPending = $account->is_two_factor_pending((int) $user['id']);
    $twoFactorSecret = '';
    $twoFactorOtpAuth = '';

    if ($twoFactorPending) {
        $twoFactorSecret = $account->get_user_meta((int) $user['id'], 'two_factor_secret_pending');
        if ($twoFactorSecret) {
            $issuer = urlencode(parse_url(BASE_URL, PHP_URL_HOST) ?: 'Site');
            $label = urlencode($user['user_email'] ?? $user['user_login'] ?? 'user');
            $twoFactorOtpAuth = "otpauth://totp/{$issuer}:{$label}?secret={$twoFactorSecret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        }
    }

    echo json_encode([
        'user' => $user,
        'sessions' => $sessions,
        'current_session' => session_id(),
        'two_factor_enabled' => $account->is_two_factor_enabled((int) $user['id']),
        'two_factor_pending' => $twoFactorPending,
        'two_factor_pending_secret' => $twoFactorSecret,
        'two_factor_pending_otpauth' => $twoFactorOtpAuth,
    ]);
    exit;
}

if ($method === 'PUT' || $method === 'PATCH') {
if (!mol_require_valid_nonce('global_csrf', $input)) {
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
    // Rate limit account changes (prevent brute-force / abuse)
    $rateKey = 'account_update_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate = mol_rate_limit($rateKey, 10, 60 * 5); // 10 updates per 5 minutes
    if (!$rate['allowed']) {
        http_response_code(429);
        echo json_encode(['error' => 'Te veel verzoeken. Probeer het later opnieuw.']);
        exit;
    }

    if (!empty($input['check_unique'])) {
        // Quick uniqueness check (used for realtime validation)
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
        echo json_encode(['success' => true]);
        exit;
    }

    $newEmail = strtolower(trim((string) ($data['user_email'] ?? '')));

    if ($newEmail !== '') {
        $other = $account->get_user_by_email($newEmail);
        if ($other && (int) ($other['id'] ?? 0) !== $userId) {
            http_response_code(409);
            echo json_encode(['error' => 'Dit e-mailadres is al in gebruik']);
            exit;
        }
    }

    if (!empty($data['user_url'])) {
        $userUrl = trim((string) $data['user_url']);

        // Allow entering `example.com` by auto-prefixing https://
        if ($userUrl !== '' && !preg_match('#^https?://#i', $userUrl)) {
            $userUrl = 'https://' . $userUrl;
        }

        if (!filter_var($userUrl, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige website-URL. Gebruik een volledige URL (inclusief http/https).']);
            exit;
        }
        $data['user_url'] = $userUrl;
    }

        // If email changed, require verification via email link.
        $currentEmail = strtolower(trim((string) ($currentUser['user_email'] ?? '')));
        if ($newEmail !== '' && $newEmail !== $currentEmail) {
            $selector = bin2hex(random_bytes(10));
            $token = bin2hex(random_bytes(20));
            $tokenHash = password_hash($token, PASSWORD_DEFAULT);
            $expires = time() + (60 * 60 * 3); // 3 hours

            $payload = json_encode([
                'user_id' => $userId,
                'new_email' => $newEmail,
                'token_hash' => $tokenHash,
                'expires' => $expires,
            ], JSON_THROW_ON_ERROR);

            mol_audit($auth->id() ?? 0, $userId, 'email_change_requested', ['new_email' => $newEmail]);

            // Remove any existing pending requests for this user.
            $account->delete_user_meta_like($userId, 'pending_email_change_%');

            // Store pending request keyed by selector.
            $account->set_user_meta($userId, 'pending_email_change_' . $selector, $payload);

            // Send verification email
            $verifyUrl = BASE_URL . '/dashboard/verify-email?selector=' . urlencode($selector) . '&token=' . urlencode($token);
            $subject = 'Bevestig uw e-mailadres';
            $message = "Hallo " . ($currentUser['display_name'] ?? $currentUser['user_login']) . ",\n\n" .
                "U heeft gevraagd uw e-mailadres te wijzigen. Klik op de volgende link om te bevestigen:\n\n" .
                $verifyUrl . "\n\n" .
                "Als u dit niet hebt aangevraagd, kunt u deze e-mail negeren.";

            email($newEmail, $subject, $message);

            // Do not update the email yet.
            unset($data['user_email']);

            $pendingEmailNotice = 'We hebben een bevestigingsmail gestuurd naar het nieuwe adres. Voltooi de verificatie om het e-mailadres te wijzigen.';
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

        $newPass = (string) $input['new_password'];
        if (strlen($newPass) < 8 || !preg_match('/[0-9]/', $newPass) || !preg_match('/[A-Z]/', $newPass)) {
            http_response_code(400);
            echo json_encode(['error' => 'Wachtwoord moet minimaal 8 tekens bevatten, incl. een hoofdletter en een cijfer']);
            exit;
        }

        $data['user_pass'] = $auth->hash_password($newPass);
        mol_audit($auth->id() ?? 0, $auth->id() ?? null, 'password_changed');
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

    $response = ['user' => $user, 'updated' => $data];
    if (!empty($pendingEmailNotice)) {
        $response['pending_email_notice'] = $pendingEmailNotice;
    }

    echo json_encode($response);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
