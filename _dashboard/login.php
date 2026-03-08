<?php

    session_start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Haal user op
        $stmt = $link->prepare("SELECT * FROM users WHERE user_login = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['user_pass'])) {
            // Login succesvol
            $_SESSION['user_id'] = $user['ID'];
            $_SESSION['user_name'] = $user['user_login'];
            header('Location: /dashboard');
            exit;
        } else {
            $error = "Ongeldige gebruikersnaam of wachtwoord";
        }
    }
    
?>

<?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body { font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        form { background: #fff; padding: 2rem; border-radius: 6px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input { display: block; margin-bottom: 1rem; padding: 0.5rem; width: 100%; }
        button { padding: 0.5rem 1rem; background: #0073aa; color: #fff; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #005177; }
    </style>
</head>
<body>
    <form method="POST" action="/login">
        <h2>Login</h2>
        <input type="text" name="username" placeholder="Gebruikersnaam" required>
        <input type="password" name="password" placeholder="Wachtwoord" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>

