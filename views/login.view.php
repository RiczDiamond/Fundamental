<?php
$error = '';
$email = '';
$rememberChecked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $rememberChecked = isset($_POST['remember']);

    if (!$email || !$password) {
        $error = "Vul alle velden correct in";
    } elseif ($auth->login($email, $password, $rememberChecked)) {
        // Succes, redirect naar dashboard
        header('Location: /dashboard');
        exit;
    } else {
        $error = "Login mislukt, controleer je gegevens";
        sleep(1); // eenvoudige brute-force bescherming
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Login - Fundamental CMS</title>
    <style>
        /* Algemene body styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        /* Login container */
        .login-container {
            background-color: #fff;
            padding: 40px 50px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }

        h1 {
            margin-bottom: 25px;
            color: #333;
        }

        /* Input velden */
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }

        /* Button */
        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #45a049;
        }

        /* Error bericht */
        p.error {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
        }

        /* Label styling */
        label {
            font-weight: bold;
            display: block;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>

        <?php if (!empty($error)) : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="/login" method="POST">
            <label>Email:</label>
            <input 
                type="email" 
                name="email" 
                value="<?= htmlspecialchars($email ?? '') ?>" 
                autocomplete="email" 
                required
            >

            <label>Wachtwoord:</label>
            <input 
                type="password" 
                name="password" 
                autocomplete="current-password" 
                required
            >

            <label>
                <input type="checkbox" name="remember" <?= $rememberChecked ? 'checked' : '' ?>>
                Onthoud mij
            </label>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>