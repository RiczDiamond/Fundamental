<?php

// AJAX endpoint to send 2FA recovery codes via email.
// This is intended for users who have 2FA enabled but cannot access their authenticator app.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim((string) ($input['username'] ?? ''));

// Avoid leaking whether a user exists.
// Always return success and send an email only if we can.

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Voer een gebruikersnaam of e-mailadres in.']);
    exit;
}

$account = new Account($link);
$user = filter_var($username, FILTER_VALIDATE_EMAIL)
    ? $account->get_user_by_email($username)
    : $account->get_user_by_login($username);

if (!$user) {
    // Always return success so attackers cannot enumerate accounts.
    echo json_encode(['success' => true]);
    exit;
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => true]);
    exit;
}

// Only trigger if 2FA is enabled.
if (!$account->is_two_factor_enabled($userId)) {
    echo json_encode(['success' => true]);
    exit;
}

// Generate new recovery codes and email them.
$codes = $account->generate_two_factor_recovery_codes($userId);

$subject = 'Herstelcodes voor 2FA';
$body = "Hallo " . ($user['display_name'] ?? $user['user_login']) . ",\n\n";
$body .= "Je hebt gevraagd om nieuwe herstelcodes voor je 2FA. Gebruik één van deze codes als je geen toegang hebt tot je authenticator-app.\n\n";
$body .= "Bewaar deze codes op een veilige plek; ze werken maar één keer.\n\n";
foreach ($codes as $code) {
    $body .= "- " . $code . "\n";
}
$body .= "\nAls je dit niet hebt aangevraagd, negeer dan dit bericht.\n";

$email = $user['user_email'] ?? '';
if ($email) {
    email($email, $subject, $body);
}

mol_audit($userId, $userId, '2fa_recovery_sent', ['username' => $user['user_login'] ?? '', 'email' => $email]);

echo json_encode(['success' => true]);
