<?php

    if (is_user_logged_in()) {

        mol_safe_redirect('/dashboard/pages');

    }

    $success = '';
    $selector = $_GET['selector'] ?? $_POST['selector'] ?? '';
    $token = $_GET['token'] ?? $_POST['token'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!mol_require_valid_nonce('dashboard_forgot')) {
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        }

        if (empty($error)) {
            $action = $_POST['action'] ?? 'request';

            // Request a password reset link
            if ($action === 'request') {
                $input = trim((string) ($_POST['username'] ?? ''));

                $repo = new Account($link);

                if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $user = $repo->get_user_by_email($input);
                } else {
                    $user = $repo->get_user_by_login($input);
                }

                // Always show generic success message to avoid account enumeration
                $success = 'Als het e-mailadres bestaat, ontvangt u zojuist een link om uw wachtwoord te herstellen.';

                if ($user) {
                    try {
                        $selector = bin2hex(random_bytes(9));
                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $expires = time() + 3600; // 1 uur

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

                        $sendError = null;
                        $toEmail = $user['user_email'] ?? '';
                        $toName = $user['user_login'] ?? '';

                        if ($toEmail !== '') {
                            // use the global wrapper introduced in helpers.php
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
                        if ($result !== true) {
                            error_log('Forgot-password: failed to send email to ' . $toEmail . '. Error: ' . $result);
                        }
                        }
                    } catch (Throwable $e) {
                        error_log('Password reset generation failed: ' . $e->getMessage());
                    }
                        // Clear selector/token variables so the success message is shown
                        $selector = '';
                        $token = '';
                }
            }

            // Submit new password using selector+token
            if ($action === 'reset') {
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['new_password_confirm'] ?? '');

                if ($new === '' || $confirm === '') {
                    $error = 'Vul beide wachtwoordvelden in.';
                } elseif ($new !== $confirm) {
                    $error = 'Wachtwoorden komen niet overeen.';
                } elseif (strlen($new) < 8) {
                    $error = 'Wachtwoord moet minstens 8 tekens lang zijn.';
                } else {
                    $repo = new Account($link);
                    $selector = $_POST['selector'] ?? '';
                    $token = $_POST['token'] ?? '';

                    $stored = $repo->get_reset_token($selector);
                    if (!$stored) {
                        $error = 'Ongeldige of verlopen link.';
                    } else {
                        $now = time();
                        $expiresTs = strtotime((string) ($stored['expires'] ?? '1970-01-01')) ?: 0;
                        if ($expiresTs < $now) {
                            $repo->delete_reset_token($selector);
                            $error = 'De link is verlopen.';
                        } else {
                            $hash = hash('sha256', $token);
                            if (!hash_equals((string) ($stored['token_hash'] ?? ''), $hash)) {
                                $error = 'Ongeldige link.';
                            } else {
                                $userId = (int) ($stored['user_id'] ?? 0);
                                if ($userId <= 0) {
                                    $error = 'Ongeldige link.';
                                } else {
                                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                                    $repo->update_user_password($userId, $newHash);
                                    $repo->delete_reset_token($selector);
                                    $success = 'Uw wachtwoord is gewijzigd. U kunt nu inloggen.';

                                    // clear selector/token so UI shows success message instead of
                                    // re-rendering the reset form below
                                    $selector = '';
                                    $token = '';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Dashboard</title>
    <link href="/css/login.css" rel="stylesheet" type="text/css" media="all">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Saira:wght@300;400;700&family=Roboto&display=swap" rel="stylesheet">
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('fully-loaded');
        });
    </script>

    <!-- Main container -->
    <div class="login-container">
        <!-- Left side - Visual section (hidden on mobile, visible on desktop) -->
        <div class="visual-section">
            <div class="image"></div>
            <div class="text">
                <h1>Welkom terug</h1>
                <h2>Bij ons dashboard</h2>
            </div>
        </div>

        <!-- Right side - Form section -->
        <div class="form-section">
            <a href="/" class="logo" aria-label="Home"></a>
            
            <div class="form-container <?php echo !empty($error) ? 'error' : ''; ?>">
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo esc_html($error); ?>
                    </div>
                <?php endif; ?>

                <div class="login-header">
                    <h2>Wachtwoord vergeten</h2>
                    <p>Voer uw e-mailadres in om uw wachtwoord opnieuw in te stellen</p>
                </div>

                <?php if (!empty($success) && empty($selector)): ?>
                    <div class="success-message">
                        <?php echo esc_html($success); ?>
                    </div>
                    <a href="/login" class="forgot-link">Terug naar Login</a>
                <?php elseif (( !empty($selector) && !empty($token) ) || ( $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset' && empty($success) )): ?>
                    <form method="POST" action="/wachtwoord-vergeten" id="resetForm">
                        <?php mol_nonce_field('dashboard_forgot'); ?>
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="selector" value="<?php echo esc_attr($selector); ?>">
                        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                        <div class="input-group">
                            <label for="new_password">Nieuw wachtwoord</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Nieuw wachtwoord" required>
                        </div>

                        <div class="input-group">
                            <label for="new_password_confirm">Bevestig nieuw wachtwoord</label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" placeholder="Bevestig nieuw wachtwoord" required>
                        </div>

                        <button type="submit" class="button" id="submitBtn">Wachtwoord wijzigen</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="/wachtwoord-vergeten" id="requestForm">
                        <?php mol_nonce_field('dashboard_forgot'); ?>
                        <input type="hidden" name="action" value="request">

                        <div class="input-group">
                            <label for="username">E-mail of gebruikersnaam</label>
                            <input 
                                type="text" 
                                id="username"
                                name="username" 
                                placeholder="Uw e-mailadres of gebruikersnaam" 
                                required
                                value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
                            >
                        </div>

                        <button type="submit" class="button" id="submitBtn">Stuur reset-link</button>

                        <a href="/login" class="forgot-link">Terug naar Login</a>
                    </form>
                <?php endif; ?>
            </div>

            <a href="/" class="website-link">Naar de website</a>
        </div>
    </div>

    <script src="/js/jq.js" type="text/javascript"></script>
    <!-- <script src="/js/admin.js" type="text/javascript"></script> -->

    <script>
        // Form loading state
        const form = document.querySelector('.form-container form');
        const submitBtn = document.getElementById('submitBtn');

        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                submitBtn.classList.add('loading', 'in-progress');
                submitBtn.innerHTML = 'Verwerken...';
            });
        }

        // Remove loading state if back button is used
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && submitBtn) {
                submitBtn.classList.remove('loading', 'in-progress');
                // Reset text depending on current form
                submitBtn.innerHTML = submitBtn.dataset.defaultText || 'Versturen';
            }
        });
    </script>
</body>
</html>