<?php

    if (is_user_logged_in()) {

        mol_safe_redirect('/dashboard/pages');

    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (!mol_require_valid_nonce('dashboard_login')) {
        
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        
        }

        if (empty($error)) {
            
            $user = mol_signon([
                'user_login' => $_POST['username'] ?? '',
                'user_password' => $_POST['password'] ?? '',
                'remember' => !empty($_POST['remember']),
            ]);

            if ($user !== false) {
            
                mol_safe_redirect('/dashboard/pages');
            
            }

            $error = 'Ongeldige gebruikersnaam of wachtwoord';
        
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
                    <h2>Inloggen</h2>
                    <p>Voer uw gegevens in om verder te gaan</p>
                </div>

                <form method="POST" action="/login" id="loginForm">
                    <?php mol_nonce_field('dashboard_login'); ?>

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

                    <div class="input-group">
                        <label for="password">Wachtwoord</label>
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            placeholder="••••••••" 
                            required
                        >
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

    <script src="/js/jq.js" type="text/javascript"></script>
    <!-- <script src="/js/admin.js" type="text/javascript"></script> -->

    <script>
        // Form loading state
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');

        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                submitBtn.classList.add('loading', 'in-progress');
                submitBtn.innerHTML = 'Bezig met inloggen...';
            });
        }

        // Remove loading state if back button is used
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                submitBtn.classList.remove('loading', 'in-progress');
                submitBtn.innerHTML = 'Inloggen';
            }
        });
    </script>
</body>
</html>