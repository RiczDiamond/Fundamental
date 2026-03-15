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

    if (mol_current_user_can('view_audit_log')) {
        $navItems['audit'] = 'Audit log';
    }

    if (mol_current_user_can('edit_roles')) {
        $navItems['roles'] = 'Rollen';
    }

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
    <link href="/resources/style/admin.css" rel="stylesheet">
    <script src="/resources/js/jq.js"></script>
    <script src="/resources/js/admin.js"></script>
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <img src="/molwebsites-brand.svg" alt="Dashboard" style="width:120px; height:auto;">
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
        <?php
            $avatarUrl = '';
            if (!empty($user['user_email'])) {
                $avatarUrl = mol_gravatar_url($user['user_email'], 40);
            }
        ?>
        <header class="topbar">
            <h1><?php echo esc_attr($navItems[$section] ?? 'Dashboard'); ?></h1>
            <div class="user">
                <div class="avatar">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo esc_attr($avatarUrl); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo esc_html($initials); ?>
                    <?php endif; ?>
                </div>
                <span><?php echo esc_attr($displayName); ?></span>
                <form method="POST" action="/dashboard/logout" style="margin:0;">
                    <button type="submit" class="btn-secondary" style="padding:8px 14px;">Uitloggen</button>
                </form>
            </div>
        </header>

        <main class="content">
