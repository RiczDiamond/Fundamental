<?php

// AJAX endpoint for forgot-password flow.
// Accepts JSON POST bodies.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

// Quick debug: ensure this script is executed when called.
@file_put_contents(__DIR__ . '/csrf-debug.log', '[' . date('c') . "] reached forgot-password endpoint (DIR=" . DIR . ")\n", FILE_APPEND | LOCK_EX);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

@file_put_contents(__DIR__ . '/csrf-debug.log', '[' . date('c') . "] request body start\n", FILE_APPEND | LOCK_EX);
@file_put_contents(
    __DIR__ . '/csrf-debug.log',
    '[' . date('c') . "] request body: " . json_encode($input) . "\n",
    FILE_APPEND | LOCK_EX
);

@file_put_contents(
    __DIR__ . '/csrf-debug.log',
    '[' . date('c') . "] MAIL_DEBUG: " . (MAIL['DEBUG'] ?? 'null') . "\n",
    FILE_APPEND | LOCK_EX
);
@file_put_contents(__DIR__ . '/csrf-debug.log', '[' . date('c') . "] request body end\n", FILE_APPEND | LOCK_EX);

// Log runtime email config when we hit this endpoint.
@file_put_contents(
    __DIR__ . '/forgot-debug.log',
    '[' . date('c') . "] mail config: host=" . (MAIL['HOST'] ?? '(none)') . " user=" . (MAIL['USER'] ?? '(none)') . " debug=" . (MAIL['DEBUG'] ?? '(none)') . "\n",
    FILE_APPEND | LOCK_EX
);

// CSRF check (uses the same nonce as the dashboard form).
if (!mol_require_valid_nonce('global_csrf')) {
    // Debug helper: write some CSRF state to a log file (short tokens) for troubleshooting.
    $postedAction = $_POST['_nonce_action'] ?? $_SERVER['HTTP_X_CSRF_ACTION'] ?? '';
    $postedNonce = $_POST['_nonce'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $storedNonce = $_SESSION['nonces']['global_csrf'] ?? null;

    $debug = sprintf(
        "%s - CSRF fail: postedAction=%s postedNonce=%s stored=%s\n",
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

// Basic rate limiting to reduce abuse.
$rateKey = 'forgot_password_' . ($input['action'] ?? 'request');
$rate = mol_rate_limit($rateKey, 6, 60 * 15); // 6 requests per 15 minutes
if (!$rate['allowed']) {
    http_response_code(429);
    echo json_encode(['error' => 'Te veel verzoeken. Probeer het later opnieuw.']);
    exit;
}

$action = strtolower(trim((string) ($input['action'] ?? 'request')));
$repo = new Account($link);

if ($action === 'request') {
    $inputValue = trim((string) ($input['username'] ?? ''));

    // Debug: log the request, including the submitted value.
    @file_put_contents(
        __DIR__ . '/forgot-debug.log',
        '[' . date('c') . "] request: {$inputValue}\n",
        FILE_APPEND | LOCK_EX
    );

    // Always show success message to avoid account enumeration.
    $response = ['success' => 'Als het e-mailadres bestaat, ontvangt u zojuist een link om uw wachtwoord te herstellen.'];

    if ($inputValue !== '') {
        if (filter_var($inputValue, FILTER_VALIDATE_EMAIL)) {
            $user = $repo->get_user_by_email($inputValue);
        } else {
            $user = $repo->get_user_by_login($inputValue);
        }

        if ($user) {
            @file_put_contents(
                __DIR__ . '/forgot-debug.log',
                '[' . date('c') . "] user found: {$user['user_email']} (id={$user['id']})\n",
                FILE_APPEND | LOCK_EX
            );

            try {
                $selector = bin2hex(random_bytes(9));
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expires = time() + 3600;

                $payload = [
                    'user_id' => (int) $user['id'],
                    'token_hash' => $tokenHash,
                    'expires' => date('Y-m-d H:i:s', $expires),
                ];

                $repo->save_reset_token($selector, $payload);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetLink = $scheme . '://' . $host . '/wachtwoord-vergeten?selector=' . $selector . '&token=' . $rawToken;

                $subject = 'Wachtwoord opnieuw instellen';
                $body = "Hallo " . ($user['user_login'] ?? '') . ",\n\n";
                $body .= "Er is een verzoek gedaan om het wachtwoord van uw account opnieuw in te stellen.\n\n";
                $body .= "Klik op de volgende link om uw wachtwoord te wijzigen (1 uur geldig):\n" . $resetLink . "\n\n";
                $body .= "Als u dit niet heeft aangevraagd, kunt u deze e-mail negeren.\n\nGroeten,\nWebsite\n";

                $toEmail = $user['user_email'] ?? '';
                if ($toEmail !== '') {
                    $result = email(
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

                    @file_put_contents(
                        __DIR__ . '/forgot-debug.log',
                        '[' . date('c') . "] mail result for {$toEmail}: " . (is_bool($result) ? ($result ? 'true' : 'false') : $result) . "\n",
                        FILE_APPEND | LOCK_EX
                    );

                    if ($result !== true) {
                        $errorMessage = 'Forgot-password: failed to send email to ' . $toEmail . '. Error: ' . $result;
                        error_log($errorMessage);

                        if (!empty(MAIL['DEBUG'])) {
                            $response['debug'] = $errorMessage;
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Password reset generation failed: ' . $e->getMessage());
            }
        }
    }

    echo json_encode($response);
    exit;
}

if ($action === 'reset') {
    $selector = trim((string) ($input['selector'] ?? ''));
    $token = trim((string) ($input['token'] ?? ''));
    $new = (string) ($input['new_password'] ?? '');
    $confirm = (string) ($input['new_password_confirm'] ?? '');

    if ($new === '' || $confirm === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Vul beide wachtwoordvelden in.']);
        exit;
    }

    if ($new !== $confirm) {
        http_response_code(400);
        echo json_encode(['error' => 'Wachtwoorden komen niet overeen.']);
        exit;
    }

    if (strlen($new) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Wachtwoord moet minstens 8 tekens lang zijn.']);
        exit;
    }

    $stored = $repo->get_reset_token($selector);
    if (!$stored) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige of verlopen link.']);
        exit;
    }

    $now = time();
    $expiresTs = strtotime((string) ($stored['expires'] ?? '1970-01-01')) ?: 0;
    if ($expiresTs < $now) {
        $repo->delete_reset_token($selector);
        http_response_code(400);
        echo json_encode(['error' => 'De link is verlopen.']);
        exit;
    }

    $hash = hash('sha256', $token);
    if (!hash_equals((string) ($stored['token_hash'] ?? ''), $hash)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige link.']);
        exit;
    }

    $userId = (int) ($stored['user_id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige link.']);
        exit;
    }

    $auth = new Auth($link);
    $newHash = $auth->hash_password($new);
    $repo->update_user_password($userId, $newHash);
    $repo->delete_reset_token($selector);

    echo json_encode(['success' => 'Uw wachtwoord is gewijzigd. U kunt nu inloggen.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ongeldige actie']);
