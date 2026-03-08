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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Fundamental CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/partials/header.php'; ?>
<main class="container py-4 site-main">
    <section class="auth-shell">
        <article class="auth-card">
            <p class="auth-kicker">Nieuw account</p>
            <h1 class="h2 mb-2">Register</h1>
            <p class="site-lead mb-4">Maak een account aan en beheer je site vanuit dezelfde visuele stijl als de rest van het platform.</p>

            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)) : ?>
                <div class="alert alert-success" role="alert"><?php echo $success; ?></div>
            <?php endif; ?>

            <form class="auth-form auth-grid" action="/register" method="POST">
                <div>
                    <label class="form-label" for="register_email">Email</label>
                    <input class="form-control" id="register_email" type="email" name="email" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? '')); ?>" required>
                </div>

                <div>
                    <label class="form-label" for="register_username">Gebruikersnaam</label>
                    <input class="form-control" id="register_username" type="text" name="username" value="<?php echo htmlspecialchars((string)($_POST['username'] ?? '')); ?>" required>
                </div>

                <div>
                    <label class="form-label" for="register_password">Wachtwoord</label>
                    <input class="form-control" id="register_password" type="password" name="password" required>
                </div>

                <div>
                    <label class="form-label" for="register_password_confirm">Bevestig wachtwoord</label>
                    <input class="form-control" id="register_password_confirm" type="password" name="password_confirm" required>
                </div>

                <button class="btn btn-primary w-100" type="submit">Register</button>
            </form>

            <div class="auth-links-row">
                <a class="btn btn-outline-secondary btn-sm" href="/login">Ik heb al een account</a>
                <a class="btn btn-outline-secondary btn-sm" href="/">Terug naar site</a>
            </div>
        </article>

        <aside class="auth-side-card">
            <p class="auth-kicker">Waarom deze stijl</p>
            <h2 class="h3 text-white">De auth-flow voelt nu als onderdeel van dezelfde website.</h2>
            <p>Geen losse grijze formulieren meer, maar een consistente ervaring die beter past bij een zakelijke of editorial site.</p>
            <ul class="auth-side-list">
                <li>Duidelijkere hiërarchie tussen acties en tekst</li>
                <li>Meer premium kleur- en typografiecombinatie</li>
                <li>Mobiel en desktop beide leesbaar</li>
            </ul>
        </aside>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>