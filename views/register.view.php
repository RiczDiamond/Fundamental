<?php

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Basis validatie
        if (empty($email) || empty($username) || empty($password)) {
            $error = "Vul alle velden in.";
        } elseif ($password !== $password_confirm) {
            $error = "Wachtwoorden komen niet overeen.";
        } else {
            // Check of email of username al bestaat
            $stmt = $link->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $error = "Email of gebruikersnaam is al in gebruik.";
            } else {
                // Maak nieuw account aan
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $link->prepare("INSERT INTO users (email, username, password, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                $stmt->execute([$email, $username, $hash]);

                $success = "Account succesvol aangemaakt. Je kan nu <a href='/login'>inloggen</a>.";
            }
        }
    }

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Register - Fundamental CMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .register-container {
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

        input[type="text"],
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

        p.error {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
        }

        p.success {
            color: green;
            margin-bottom: 15px;
            font-weight: bold;
        }

        label {
            font-weight: bold;
            display: block;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Register</h1>

        <?php if (!empty($error)) : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>

        <form action="/register" method="POST">
            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Gebruikersnaam:</label>
            <input type="text" name="username" required>

            <label>Wachtwoord:</label>
            <input type="password" name="password" required>

            <label>Bevestig wachtwoord:</label>
            <input type="password" name="password_confirm" required>

            <button type="submit">Register</button>
        </form>
    </div>
</body>
</html>