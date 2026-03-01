<?php



// Classes instantieren
$cookie = new cookie($pdo, COOKIE_ENCRYPTION_KEY);
$session = new Session($pdo, $cookie);
$auth = new Auth($pdo);

// Rate limiting check
$rateLimiter = new RateLimiter($pdo);
if ($rateLimiter->isBlocked($_SERVER['REMOTE_ADDR'])) {
    $error = "Te veel inlogpogingen. Probeer het later opnieuw.";
}

// Verwerk login
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    
    // CSRF validatie
    if (!$cookie->csrfValidate($_POST['csrf_token'] ?? '')) {
        $error = "Ongeldige aanvraag. Vernieuw de pagina.";
    } else {
        
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validatie
        if (empty($identifier) || empty($password)) {
            $error = "Vul alle velden in";
        } else {
            
            // Rate limit check
            if ($rateLimiter->check($_SERVER['REMOTE_ADDR'])) {
                
                // Login poging
                $result = $auth->login($identifier, $password, $remember);
                
                if ($result['success']) {
                    // Sla sessie data op
                    $session->setAuth($result['user']['id'], [
                        'username' => $result['user']['username'],
                        'email' => $result['user']['email'],
                        'login_method' => 'password'
                    ]);
                    
                    // Regenerate voor security
                    $session->regenerate(true);
                    
                    // Redirect naar dashboard
                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                    header("Location: " . $redirect);
                    exit;
                    
                } else {
                    $error = $result['error'];
                    $rateLimiter->increment($_SERVER['REMOTE_ADDR']);
                }
                
            } else {
                $error = "Te veel inlogpogingen. Probeer het over " . $rateLimiter->timeRemaining($_SERVER['REMOTE_ADDR']) . " minuten opnieuw.";
            }
        }
    }
}

// Genereer CSRF token
$csrfToken = $cookie->csrfToken();

// Check of gebruiker al is ingelogd
if ($auth->check()) {
    header("Location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - <?= SITE_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }

        .input-wrapper input.error {
            border-color: #e74c3c;
            background: #fdf2f2;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 13px;
            user-select: none;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-password:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spinner 0.8s linear infinite;
        }

        @keyframes spinner {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-error {
            background: #fdf2f2;
            color: #e74c3c;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .security-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        .security-info svg {
            vertical-align: middle;
            margin-right: 5px;
        }

        @media (max-width: 480px) {
            .login-container {
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

<div class="login-container">
    <div class="login-header">
        <h1>Welkom terug</h1>
        <p>Log in om verder te gaan</p>
    </div>
    
    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label for="identifier">Gebruikersnaam of E-mail</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="identifier" 
                        name="identifier" 
                        value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                        placeholder="naam@example.com"
                        required
                        autocomplete="username email"
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <span class="password-toggle" onclick="togglePassword()">Toon</span>
                </div>
            </div>

            <div class="options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span>Onthoud mij</span>
                </label>
                <a href="forgot-password.php" class="forgot-password">Wachtwoord vergeten?</a>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">
                Inloggen
            </button>
        </form>

        <div class="register-link">
            Nog geen account? <a href="register.php">Registreer hier</a>
        </div>

        <div class="security-info">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Beveiligde verbinding • AES-256 encryptie
        </div>
    </div>
</div>

<script>
    // Password toggle
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

    // Form validatie
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const identifier = document.getElementById('identifier');
        const password = document.getElementById('password');
        const submitBtn = document.getElementById('submitBtn');
        let hasError = false;

        // Reset errors
        identifier.classList.remove('error');
        password.classList.remove('error');

        // Validatie
        if (identifier.value.trim() === '') {
            identifier.classList.add('error');
            hasError = true;
        }

        if (password.value === '') {
            password.classList.add('error');
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            return false;
        }

        // Loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.textContent = '';
    });

    // Enter key support
    document.getElementById('password').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('loginForm').submit();
        }
    });
</script>

</body>
</html>