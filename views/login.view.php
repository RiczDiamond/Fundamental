<?php
$error = (string)($error ?? '');
$email = (string)($email ?? '');
$rememberChecked = (bool)($rememberChecked ?? false);
$csrfToken = (string)($csrfToken ?? '');
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fundamental CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/partials/header.php'; ?>
<main class="container py-4 site-main">
    <section class="auth-shell">
        <article class="auth-card">
            <p class="auth-kicker">Terug in je workspace</p>
            <h1 class="h2 mb-2">Login</h1>
            <p class="site-lead mb-4">Log in om pages, blogposts en content vanuit dezelfde omgeving te beheren.</p>

            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="auth-form auth-grid" action="/login" method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">

                <div>
                    <label class="form-label" for="login_email">Email</label>
                    <input
                        class="form-control"
                        id="login_email"
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div>
                    <label class="form-label" for="login_password">Wachtwoord</label>
                    <input
                        class="form-control"
                        id="login_password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <label class="form-check" for="login_remember">
                    <input id="login_remember" type="checkbox" name="remember" <?= $rememberChecked ? 'checked' : '' ?>>
                    <span>Onthoud mij</span>
                </label>

                <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>

            <div class="auth-links-row">
                <a class="btn btn-outline-secondary btn-sm" href="/register">Nog geen account?</a>
                <a class="btn btn-outline-secondary btn-sm" href="/">Terug naar site</a>
            </div>
        </article>

        <aside class="auth-side-card">
            <p class="auth-kicker">Fundamental CMS</p>
            <h2 class="h3 text-white">Een rustigere, meer premium uitstraling voor je contentomgeving.</h2>
            <p>Deze login sluit nu aan op dezelfde look als de publieke site, in plaats van een losstaand standaardformulier.</p>
            <ul class="auth-side-list">
                <li>Consistente typografie en kleuren</li>
                <li>Betere focus states en formulierhiërarchie</li>
                <li>Zelfde branding als pages en blog</li>
            </ul>
        </aside>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>