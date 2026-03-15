<?php

    // Shared header for dashboard views.
    // Provides sidebar + topbar with user info.

    $auth = new Auth($link);
    $user = $auth->current_user();
    $displayName = $user['display_name'] ?? ($user['user_login'] ?? '');
    $initials = strtoupper(substr($displayName, 0, 1));

    $navItems = [
        'home' => 'Dashboard',
        'account' => 'Account',
        'posts' => 'Posts',
        'users' => 'Users',
    ];

    function dashboardNavItem(string $key, string $label, string $current): string
    {
        $active = $key === $current ? ' active' : '';
        $icon = '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
        return "<a class=\"nav-item{$active}\" href=\"/dashboard/{$key}\">{$icon}{$label}</a>";
    }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo esc_attr($navItems[$section] ?? 'Dashboard'); ?></title>
    <meta name="csrf-token" content="<?php echo esc_attr(mol_get_nonce('global_csrf')); ?>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="/resources/js/jq.js"></script>
    <script src="/resources/js/admin.js"></script>
    <style>
        :root {
            --bg: #f0f3f8;
            --card: #ffffff;
            --text: #1e2330;
            --muted: #747d93;
            --accent: #00c9a7;
            --accent-dark: #00a98c;
            --border: #e4e9f2;
            --shadow: 0 2px 18px rgba(30,35,48,0.07);
            --sidebar: #1e2330;
            --sidebar-text: #9aa3b8;
            --sidebar-active: #ffffff;
            --sidebar-active-bg: #2c3347;
            --sidebar-width: 240px;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar);
            padding: 18px 12px;
            display: flex; flex-direction: column;
            gap: 12px;
        }

        .brand {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; border-radius: 12px;
            background: rgba(255,255,255,0.08);
            margin-bottom: 16px;
        }

        .brand-title { font-weight: 700; letter-spacing: 0.5px; color: #fff; font-size: 14px; }
        .brand-sub { font-size: 10px; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.6px; }

        .nav { display: flex; flex-direction: column; gap: 6px; }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; border-radius: 10px;
            color: var(--sidebar-text); text-decoration: none; font-weight: 500;
            font-size: 13px;
            transition: background 0.2s, color 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: var(--sidebar-active-bg); color: var(--sidebar-active); }

        .main { flex: 1; display: flex; flex-direction: column; }
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 26px; background: var(--bg); border-bottom: 1px solid var(--border);
        }
        .topbar h1 { margin: 0; font-size: 18px; letter-spacing: 0.2px; }

        .user { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--muted); }
        .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, #4a7cf7, #00c9a7);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700;
        }

        .content { flex: 1; overflow-y: auto; padding: 24px 30px; }
        .card { background: var(--card); border-radius: 14px; padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .section-title { margin: 0 0 20px; font-size: 16px; letter-spacing: 0.2px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; color: var(--muted); }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: #fff; font-size: 14px; }
        button { cursor: pointer; border: none; border-radius: 10px; padding: 10px 18px; font-weight: 600; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-secondary { background: #f4f6fc; color: var(--text); }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; }
        .alert-error { background: #ffe5e5; color: #8f1c1c; }
        .alert-success { background: #e6ffef; color: #1c613a; }
    </style>
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="avatar"><?php echo esc_attr($initials); ?></div>
            <div>
                <div class="brand-title">Dashboard</div>
                <div class="brand-sub"><?php echo esc_attr($displayName); ?></div>
            </div>
        </div>

        <nav class="nav">
            <?php
                foreach ($navItems as $key => $label) {
                    echo dashboardNavItem($key, $label, $section);
                }
            ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1><?php echo esc_attr($navItems[$section] ?? 'Dashboard'); ?></h1>
            <div class="user">
                <span><?php echo esc_attr($displayName); ?></span>
                <form method="POST" action="/dashboard/logout" style="margin:0;">
                    <button type="submit" class="btn-secondary" style="padding:8px 14px;">Uitloggen</button>
                </form>
            </div>
        </header>

        <main class="content">
