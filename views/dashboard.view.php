<?php
    $currentUserId = $session->get('user_id');
    $currentUser = $account->get($currentUserId);

    if (!$currentUser) {
        header('Location: /logout');
        exit;
    }

    $dashboardPages = ['overview', 'users', 'blogs', 'pages', 'profile'];
    $page = $dashboardPage ?? 'overview';
    if (!in_array($page, $dashboardPages, true)) {
        $page = 'overview';
    }

    $canDashboard = $auth->perm_check(null, 'dashboard');
    $canManageUsers = $auth->perm_check(null, 'users.manage');
    $canBlogWrite = $auth->perm_check(null, 'blog.write');
    $canPagesWrite = $auth->perm_check(null, 'pages.write');

    if (!$canDashboard) {
        http_response_code(403);
        echo 'Geen toegang tot dashboard.';
        exit;
    }

    $errors = [];
    $success = '';

    $okMessages = [
        'profile_updated' => 'Profiel bijgewerkt.',
        'password_updated' => 'Wachtwoord bijgewerkt.',
        'user_updated' => 'Gebruiker bijgewerkt.',
        'user_banned' => 'Gebruiker geblokkeerd.',
        'user_unbanned' => 'Gebruiker gedeblokkeerd.',
        'user_trashed' => 'Gebruiker verplaatst naar verwijderlijst.',
        'user_restored' => 'Gebruiker hersteld.',
    ];

    if (!empty($_GET['ok']) && isset($okMessages[$_GET['ok']])) {
        $success = $okMessages[$_GET['ok']];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedCsrf = $_POST['csrf'] ?? '';
        if (!hash_equals((string)$csrfToken, (string)$postedCsrf)) {
            $errors[] = 'Ongeldige aanvraag (CSRF).';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'profile_update') {
                $gender = trim($_POST['gender'] ?? '');
                $birthDate = trim($_POST['birth_date'] ?? '');
                $data = [
                    'id' => $currentUserId,
                    'display_name' => trim($_POST['display_name'] ?? ''),
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'username' => trim($_POST['username'] ?? ''),
                    'gender' => $gender !== '' ? $gender : null,
                    'birth_date' => $birthDate !== '' ? $birthDate : null,
                ];

                if (empty($data['email']) || empty($data['username'])) {
                    $errors[] = 'Email en gebruikersnaam zijn verplicht.';
                } elseif ($account->put($data)) {
                    header('Location: /dashboard/profile?ok=profile_updated');
                    exit;
                } else {
                    $errors[] = 'Profiel kon niet worden bijgewerkt.';
                }
            }

            if ($action === 'profile_password') {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

                if (empty($currentPassword) || empty($newPassword)) {
                    $errors[] = 'Vul alle wachtwoordvelden in.';
                } elseif ($newPassword !== $newPasswordConfirm) {
                    $errors[] = 'Nieuw wachtwoord komt niet overeen.';
                } elseif (!password_verify($currentPassword, $currentUser['password'])) {
                    $errors[] = 'Huidig wachtwoord is onjuist.';
                } elseif ($account->put(['id' => $currentUserId, 'password' => $newPassword])) {
                    header('Location: /dashboard/profile?ok=password_updated');
                    exit;
                } else {
                    $errors[] = 'Wachtwoord kon niet worden bijgewerkt.';
                }
            }

            if ($action === 'user_update') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);
                    $payload = [
                        'id' => $targetId,
                        'display_name' => trim($_POST['display_name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'username' => trim($_POST['username'] ?? ''),
                        'role' => trim($_POST['role'] ?? 'user'),
                        'status' => trim($_POST['status'] ?? 'active'),
                    ];

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->put($payload)) {
                        header('Location: /dashboard/users?ok=user_updated');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'user_ban') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);
                    $reason = trim($_POST['reason'] ?? 'Geblokkeerd via dashboard');
                    $minutes = max(0, (int)($_POST['minutes'] ?? 0));
                    $until = $minutes > 0 ? time() + ($minutes * 60) : null;

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($targetId === (int)$currentUserId) {
                        $errors[] = 'Je kunt jezelf niet blokkeren.';
                    } elseif ($account->ban($targetId, $reason, $until)) {
                        header('Location: /dashboard/users?ok=user_banned');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden geblokkeerd.';
                    }
                }
            }

            if ($action === 'user_unban') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->unban($targetId)) {
                        header('Location: /dashboard/users?ok=user_unbanned');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden gedeblokkeerd.';
                    }
                }
            }

            if ($action === 'user_trash') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($targetId === (int)$currentUserId) {
                        $errors[] = 'Je kunt jezelf niet verwijderen.';
                    } elseif ($account->trash($targetId)) {
                        header('Location: /dashboard/users?ok=user_trashed');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden verplaatst naar verwijderlijst.';
                    }
                }
            }

            if ($action === 'user_restore') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->restore($targetId)) {
                        header('Location: /dashboard/users?ok=user_restored');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden hersteld.';
                    }
                }
            }
        }
    }

    $stats = $account->get_dashboard_stats();
    $usersSearch = trim($_GET['q'] ?? '');
    $users = ($usersSearch !== '') ? $account->get_all($usersSearch) : $account->get_all();
    $trashedUsers = $account->get_trash(100);
    $profileGroups = $account->get_groups($currentUserId);
    $profilePermissions = $account->get_permissions($currentUserId);

    $activeUsers = [];
    $bannedUsers = [];
    foreach ($users as $row) {
        if (($row['status'] ?? '') === 'banned') {
            $bannedUsers[] = $row;
        } else {
            $activeUsers[] = $row;
        }
    }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fundamental CMS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; color: #1f2937; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .title { margin: 0; font-size: 24px; }
        .muted { color: #6b7280; font-size: 14px; }
        .nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .nav a { text-decoration: none; padding: 10px 14px; border-radius: 8px; color: #334155; background: #e2e8f0; font-size: 14px; }
        .nav a.active { background: #2563eb; color: #fff; }
        .card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.05); margin-bottom: 14px; }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .stat { background: #f8fafc; border-radius: 8px; padding: 12px; }
        .stat .label { font-size: 12px; color: #64748b; }
        .stat .value { font-size: 24px; font-weight: bold; margin-top: 4px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        input, select, button, textarea { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 14px; }
        input, select, textarea { background: #fff; }
        button { background: #2563eb; color: #fff; border: none; cursor: pointer; }
        button.secondary { background: #64748b; }
        button.warn { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-size: 13px; }
        .alert-ok { padding: 10px 12px; border-radius: 8px; background: #dcfce7; color: #166534; margin-bottom: 12px; }
        .alert-error { padding: 10px 12px; border-radius: 8px; background: #fee2e2; color: #991b1b; margin-bottom: 12px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.banned { background: #fee2e2; color: #991b1b; }
        .two-col { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .small { font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <h1 class="title">Dashboard</h1>
                <div class="muted">Ingelogd als <?php echo htmlspecialchars($currentUser['username'] ?? ''); ?> (<?php echo htmlspecialchars($currentUser['role'] ?? 'user'); ?>)</div>
            </div>
            <div class="row">
                <a href="/logout">Uitloggen</a>
            </div>
        </div>

        <nav class="nav">
            <a class="<?php echo $page === 'overview' ? 'active' : ''; ?>" href="/dashboard/overview">Overview</a>
            <a class="<?php echo $page === 'users' ? 'active' : ''; ?>" href="/dashboard/users">Users</a>
            <a class="<?php echo $page === 'blogs' ? 'active' : ''; ?>" href="/dashboard/blogs">Blogs</a>
            <a class="<?php echo $page === 'pages' ? 'active' : ''; ?>" href="/dashboard/pages">Pages</a>
            <a class="<?php echo $page === 'profile' ? 'active' : ''; ?>" href="/dashboard/profile">Profile</a>
        </nav>

        <?php if (!empty($success)) : ?>
            <div class="alert-ok"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error) : ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <?php require __DIR__ . '/dashboard/' . $page . '.view.php'; ?>
    </div>
</body>
</html>