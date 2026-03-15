<?php

    if (is_user_logged_in()) {

        mol_safe_redirect('/dashboard');

    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (!mol_require_valid_nonce('global_csrf')) {
        
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        
        }

        if (empty($error)) {
            
            $user = mol_signon([
                'user_login' => $_POST['username'] ?? '',
                'user_password' => $_POST['password'] ?? '',
                'remember' => !empty($_POST['remember']),
            ]);

            if ($user !== false) {
            
                mol_safe_redirect('/dashboard');
            
            }

            $error = 'Ongeldige gebruikersnaam of wachtwoord';
        
        }
    
    }

?>
<?php $globalNonce = mol_csrf_token(); ?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Dashboard</title>
    <link href="/resources/style/login.css" rel="stylesheet" type="text/css" media="all">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Saira:wght@300;400;700&family=Roboto&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo esc_attr($globalNonce); ?>">
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
                <p>Hulp nodig bij websitebeheer? Bel of mail ons gerust.</p>
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
                    <h2>Inloggen</h2>
                    <p>Voer uw gegevens in om verder te gaan</p>
                </div>

                <form method="POST" action="/login" id="loginForm">

                    <div class="input-group">
                        <label for="username">Gebruikersnaam</label>
                        <input 
                            type="text" 
                            id="username"
                            name="username" 
                            placeholder="Uw gebruikersnaam" 
                            required
                            value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
                        >
                    </div>

                    <div class="input-group password">
                        <label for="password">Wachtwoord</label>
                        <div class="input-with-toggle">
                            <input 
                                type="password" 
                                id="password"
                                name="password" 
                                class="password-toggle-enabled"
                                placeholder="••••••••" 
                                required
                            >
                            <button type="button" class="password-toggle" aria-label="Toon/verberg wachtwoord">
                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                <span class="dashicons dashicons-hidden" aria-hidden="true" style="display:none;"></span>
                            </button>
                        </div>
                    </div>

                    <div class="checkbox-wrapper">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            id="remember" 
                            value="1"
                            <?php echo !empty($_POST['remember']) ? 'checked' : ''; ?>
                        >
                        <label for="remember">Ingelogd blijven</label>
                    </div>

                    <button type="submit" class="button" id="submitBtn">
                        Inloggen
                    </button>

                    <a href="/wachtwoord-vergeten" class="forgot-link">Wachtwoord vergeten?</a>
                </form>

            </div>

            <a href="/" class="website-link">Naar de website</a>
        </div>
    </div>

    <script src="/resources/js/jq.js" type="text/javascript"></script>
    <script src="/resources/js/admin.js" type="text/javascript"></script>

</body>
</html>