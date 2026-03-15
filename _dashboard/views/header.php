<?php

    // Shared header for dashboard views.
    // Provides sidebar + topbar with user info.

    $auth = new Auth($link);
    $user = $auth->current_user();
    $displayName = $user['display_name'] ?? ($user['user_login'] ?? '');
    $initials = strtoupper(substr($displayName, 0, 1));

    $navItems = [
        'home' => ['label' => 'Dashboard', 'href' => '/dashboard'],
        'account' => ['label' => 'Account', 'href' => '/dashboard/account'],
        'posts' => ['label' => 'Posts', 'href' => '/dashboard/posts'],
    ];

    // Add each post type as its own menu item (separate from "Posts").
    $postTypes = mol_get_post_types();
    foreach ($postTypes as $type => $info) {
        $navItems['posts-' . $type] = [
            'label' => $info['labels']['plural'] ?? $type,
            'href' => '/dashboard/posts/' . $type,
            'icon' => $info['menu_icon'] ?? '',
        ];
    }



    $navItems['users'] = ['label' => 'Users', 'href' => '/dashboard/users'];

    if (mol_current_user_can('view_audit_log')) {
        $navItems['audit'] = ['label' => 'Audit log', 'href' => '/dashboard/audit'];
    }

    if (mol_current_user_can('edit_roles')) {
        $navItems['roles'] = ['label' => 'Rollen', 'href' => '/dashboard/roles'];
    }

    function dashboardNavItem(string $href, string $label, bool $active, string $icon = ''): string
    {
        $activeClass = $active ? ' active' : '';
        $iconHtml = $icon ? '<span class="nav-icon">' . $icon . '</span>' : '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
        return "<a class=\"nav-item{$activeClass}\" href=\"{$href}\">{$iconHtml}<span class=\"nav-label\">{$label}</span></a>";
    }

    $currentPostType = trim((string) ($postType ?? ''));

    // Determine current page title based on the section/post type.
    $currentTitle = 'Dashboard';
    if ($section === 'posts') {
        if ($currentPostType !== '' && isset($postTypes[$currentPostType])) {
            $currentTitle = $postTypes[$currentPostType]['labels']['plural'] ?? $currentPostType;
        } else {
            $currentTitle = $navItems['posts']['label'] ?? 'Posts';
        }
    } elseif (isset($navItems[$section]['label'])) {
        $currentTitle = $navItems[$section]['label'];
    }

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo esc_attr($currentTitle); ?></title>
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
                foreach ($navItems as $key => $item) {
                    $href = $item['href'] ?? '/dashboard/' . $key;
                    $label = $item['label'] ?? $key;

                    $active = false;
                    if ($key === 'posts') {
                        $active = ($section === 'posts' && $currentPostType === '');
                    } elseif (str_starts_with($key, 'posts-')) {
                        $type = substr($key, 6);
                        $active = ($section === 'posts' && $currentPostType === $type);
                    } else {
                        $active = $section === $key;
                    }

                    $icon = $item['icon'] ?? '';
                    echo dashboardNavItem($href, $label, $active, $icon);
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
            <h1><?php echo esc_attr($currentTitle); ?></h1>
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
