<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Initialisatie
$cookie = new cookie($pdo, COOKIE_ENCRYPTION_KEY);
$session = new Session($pdo, $cookie);
$auth = new Auth($pdo);
$rateLimiter = new RateLimiter($pdo);

// Redirect als al ingelogd
if ($auth->check() || $session->isAuthenticated()) {
    $redirect = $_GET['redirect'] ?? PROJECT['PATHS']['DASHBOARD'] ?? '/dashboard.php';
    header("Location: " . $redirect);
    exit;
}

// Verwerk login
$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Rate limiting check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($rateLimiter->isBlocked($ip)) {
        $error = "Te veel inlogpogingen. Probeer het over " . 
                 $rateLimiter->timeRemaining($ip) . " minuten opnieuw.";
    } else {
        
        // CSRF validatie
        if (!$cookie->csrfValidate($_POST['csrf_token'] ?? '')) {
            $error = "Ongeldige aanvraag. Vernieuw de pagina en probeer opnieuw.";
        } else {
            
            $identifier = trim($_POST['identifier'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            // Validatie
            if (empty($identifier) || empty($password)) {
                $error = "Vul alle velden in.";
            } elseif (strlen($password) < 1) {
                $error = "Wachtwoord is te kort.";
            } else {
                
                // Login poging
                $result = $auth->login($identifier, $password, $remember);
                
                if ($result['success']) {
                    // Reset rate limiter bij succes
                    $rateLimiter->reset($ip);
                    
                    // Sla auth data op in sessie
                    $session->setAuth((int)$result['user']['id'], [
                        'username' => $result['user']['username'],
                        'email' => $result['user']['email'],
                        'login_time' => time(),
                        'login_method' => 'password'
                    ]);
                    
                    // Regenerate sessie ID voor security
                    $session->regenerate(true);
                    
                    // Redirect
                    $redirect = $_GET['redirect'] ?? PROJECT['PATHS']['DASHBOARD'] ?? '/dashboard.php';
                    
                    // Log succesvolle login
                    if (function_exists('log_action')) {
                        log_action('login_success', [
                            'user_id' => $result['user']['id'],
                            'ip' => $ip,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                        ]);
                    }
                    
                    header("Location: " . $redirect);
                    exit;
                    
                } else {
                    $error = $result['error'];
                    $rateLimiter->increment($ip);
                    
                    // Log mislukte poging
                    if (function_exists('log_action')) {
                        log_action('login_failed', [
                            'identifier' => $identifier,
                            'ip' => $ip,
                            'reason' => $result['error']
                        ]);
                    }
                }
            }
        }
    }
}

// Genereer CSRF token
$csrfToken = $cookie->csrfToken();

// Page config
$pageTitle = 'Inloggen';
$pageDescription = 'Log in op ' . SITE_NAME;

?>
<!DOCTYPE html>
<html lang="<?= PROJECT['LOCALE']['LANGUAGE'] ?? 'nl' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= PROJECT['PATHS']['ASSETS'] ?? '/assets' ?>/img/logo.svg">
    
    <!-- Styles -->
    <style>
        :root {
            --primary: <?= PROJECT['COLORS']['PRIMARY'] ?? '#667eea' ?>;
            --primary-dark: <?= PROJECT['COLORS']['PRIMARY_DARK'] ?? '#764ba2' ?>;
            --error: #e74c3c;
            --success: #27ae60;
            --text: #2c3e50;
            --text-light: #7f8c8d;
            --bg: #f5f7fa;
            --white: #ffffff;
            --shadow: 0 10px 40px rgba(0,0,0,0.1);
            --radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .login-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 48px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 40px 32px;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: shake 0.5s ease;
            border: 1px solid transparent;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }

        .alert-error {
            background: #fdf2f2;
            color: var(--error);
            border-color: #fecaca;
        }

        .alert svg {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .input-wrap {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            background: #f8fafc;
            color: var(--text);
        }

        .form-input:hover {
            border-color: #cbd5e1;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input.error {
            border-color: var(--error);
            background: #fef2f2;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 24px 0;
            font-size: 14px;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: var(--text);
            user-select: none;
        }

        .checkbox-wrap input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            margin: -12px 0 0 -12px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: var(--white);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .divider {
            margin: 32px 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: var(--white);
            padding: 0 16px;
            color: var(--text-light);
            font-size: 14px;
            position: relative;
        }

        .register-text {
            text-align: center;
            color: var(--text-light);
            font-size: 15px;
        }

        .security-badge {
            margin-top: 32px;
            padding: 16px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-light);
        }

        .security-badge svg {
            width: 18px;
            height: 18px;
            color: var(--success);
        }

        @media (max-width: 480px) {
            body {
                padding: 0;
            }
            
            .login-card {
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            
            .login-body {
                flex: 1;
            }
        }
    </style>
</head>
<body>

<main class="login-card">
    <header class="login-header">
        <svg class="brand-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
        <h1>Welkom terug</h1>
        <p>Log in om verder te gaan</p>
    </header>

    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="form-group">
                <label class="form-label" for="identifier">Gebruikersnaam of e-mail</label>
                <div class="input-wrap">
                    <input 
                        type="text" 
                        id="identifier" 
                        name="identifier" 
                        class="form-input <?= $error && empty($identifier) ? 'error' : '' ?>"
                        value="<?= htmlspecialchars($identifier) ?>"
                        placeholder="naam@example.com"
                        required
                        autocomplete="username email"
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Wachtwoord</label>
                <div class="input-wrap">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input <?= $error && empty($_POST['password'] ?? '') ? 'error' : '' ?>"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()" tabindex="-1">
                        Toon
                    </button>
                </div>
            </div>

            <div class="options">
                <label class="checkbox-wrap">
                    <input type="checkbox" name="remember" id="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span>Onthoud mij</span>
                </label>
                <a href="<?= PROJECT['PATHS']['FORGOT_PASSWORD'] ?? '/forgot-password.php' ?>" class="link">
                    Wachtwoord vergeten?
                </a>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                Inloggen
            </button>
        </form>

        <div class="divider">
            <span>of</span>
        </div>

        <p class="register-text">
            Nog geen account? <a href="<?= PROJECT['PATHS']['REGISTER'] ?? '/register.php' ?>" class="link">Registreer je</a>
        </p>

        <div class="security-badge">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
            </svg>
            <span>Beveiligde verbinding met AES-256 encryptie</span>
        </div>
    </div>
</main>

<script>
    // Password visibility toggle
    function togglePassword() {
        const password = document.getElementById('password');
        const toggle = document.querySelector('.password-toggle');
        
        if (password.type === 'password') {
            password.type = 'text';
            toggle.textContent = 'Verberg';
        } else {
            password.type = 'password';
            toggle.textContent = 'Toon';
        }
    }

    // Form handling
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const identifier = document.getElementById('identifier');
        const password = document.getElementById('password');
        const submitBtn = document.getElementById('submitBtn');
        let hasError = false;

        // Reset errors
        identifier.classList.remove('error');
        password.classList.remove('error');

        // Validation
        if (!identifier.value.trim()) {
            identifier.classList.add('error');
            hasError = true;
        }

        if (!password.value) {
            password.classList.add('error');
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            identifier.focus();
            return false;
        }

        // Loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        submitBtn.textContent = '';
    });

    // Remove error on input
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
</script>

</body>
</html>