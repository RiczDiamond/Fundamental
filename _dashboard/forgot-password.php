<?php

if (is_user_logged_in()) {
    mol_safe_redirect('/dashboard');
}

// The forgot-password page is intentionally minimal. All processing is handled by
// the AJAX endpoint at /resources/ajax/forgot-password.php. This avoids embedding
// token validation logic in the public page and ensures any form submissions are
// protected with CSRF and rate limiting.
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord vergeten</title>
    <link href="/resources/style/login.css" rel="stylesheet" type="text/css" media="all">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Saira:wght@300;400;700&family=Roboto&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo esc_attr(mol_get_nonce('global_csrf')); ?>">
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('fully-loaded');
        });
    </script>

    <div class="login-container">
        <div class="visual-section">
            <div class="image"></div>
            <div class="text">
                <h1>Wachtwoord vergeten</h1>
                <h2>Voer uw e-mail of gebruikersnaam in</h2>
            </div>
        </div>

        <div class="form-section">
            <a href="/" class="logo" aria-label="Home"></a>

            <div class="form-container">
                <div id="forgot-alert" class="alert" style="display:none;"></div>

                <div id="forgot-request" class="forgot-block">
                    <form id="forgot-request-form">
                        <div class="input-group">
                            <label for="forgot_username">E-mail of gebruikersnaam</label>
                            <input id="forgot_username" name="username" type="text" placeholder="Uw e-mailadres of gebruikersnaam" required>
                        </div>

                        <button type="submit" class="button" id="forgot-request-submit">Stuur reset-link</button>
                    </form>
                </div>

                <div id="forgot-reset" class="forgot-block" style="display:none;">
                    <form id="forgot-reset-form">
                        <div class="input-group password">
                            <label for="forgot_new_password">Nieuw wachtwoord</label>
                            <div class="input-with-toggle">
                                <input id="forgot_new_password" name="new_password" type="password" placeholder="Nieuw wachtwoord" required>
                                <button type="button" class="password-toggle" aria-label="Toon/verberg wachtwoord">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                    <span class="dashicons dashicons-hidden" aria-hidden="true" style="display:none;"></span>
                                </button>
                            </div>
                        </div>
                        <div class="input-group password">
                            <label for="forgot_new_password_confirm">Bevestig nieuw wachtwoord</label>
                            <div class="input-with-toggle">
                                <input id="forgot_new_password_confirm" name="new_password_confirm" type="password" placeholder="Bevestig nieuw wachtwoord" required>
                                <button type="button" class="password-toggle" aria-label="Toon/verberg wachtwoord">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                    <span class="dashicons dashicons-hidden" aria-hidden="true" style="display:none;"></span>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="button" id="forgot-reset-submit">Wachtwoord wijzigen</button>
                    </form>
                </div>

                <div id="forgot-success" class="forgot-block" style="display:none;">
                    <div class="success-message" id="forgot-success-message"></div>
                    <a href="/login" class="forgot-link">Terug naar Login</a>
                </div>

            </div>

            <a href="/login" class="website-link">Terug naar Login</a>
        </div>
    </div>

    <script src="/resources/js/jq.js" type="text/javascript"></script>
    <script src="/resources/js/admin.js" type="text/javascript"></script>
</body>
</html>

                        