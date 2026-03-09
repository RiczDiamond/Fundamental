<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


?>
<?php if (!empty($_SESSION['user_id'])): ?>
<div style="position:fixed;top:0;left:0;width:100%;background:#23282d;color:#fff;font-family:sans-serif;font-size:14px;z-index:9999;">
    
    <div style="max-width:1200px;margin:auto;display:flex;justify-content:space-between;padding:8px 15px;">

        <div>
            <span style="margin-right:15px;">
                <a href="/">Home</a>
            </span>

          
        </div>

    </div>

</div>

<div style="height:32px;"></div>
<?php endif; ?>

<?php

if (empty($_SESSION['user_id'])) {
    wp_safe_redirect('/login');
}

if (isset($params['logout'])) {
    $_SESSION = [];
    session_destroy();
    wp_safe_redirect('/login');
}

$section = $url[1] ?? 'home';
$action = $url[2] ?? '';

if ($section === 'pages' && ($action === '' || $action === 'index')) {
    require_once __DIR__ . '/pages/index.php';
    return;
}

if ($section === 'pages' && $action === 'edit') {
    require_once __DIR__ . '/pages/edit.php';
    return;
}

if ($section === 'pages' && $action === 'create') {
    require_once __DIR__ . '/pages/create.php';
    return;
}

if ($section === 'media') {
    require_once __DIR__ . '/media.php';
    return;
}

if ($section === 'menus') {
    require_once __DIR__ . '/menus.php';
    return;
}

if ($section === 'contact') {
    require_once __DIR__ . '/contact.php';
    return;
}

function dashboard_count(PDO $link, string $sql, array $bind = []): int {
    $stmt = $link->prepare($sql);
    $stmt->execute($bind);
    return (int) $stmt->fetchColumn();
}

$stats = [
    'pages' => dashboard_count($link, "SELECT COUNT(*) FROM posts WHERE post_type = 'page'"),
    'published_pages' => dashboard_count($link, "SELECT COUNT(*) FROM posts WHERE post_type = 'page' AND post_status = 'publish'"),
    'draft_pages' => dashboard_count($link, "SELECT COUNT(*) FROM posts WHERE post_type = 'page' AND post_status = 'draft'"),
    'media' => dashboard_count($link, "SELECT COUNT(*) FROM posts WHERE post_type = 'attachment'"),
    'users' => dashboard_count($link, "SELECT COUNT(*) FROM users"),
];

$recentPagesStmt = $link->prepare("\n    SELECT ID, post_title, post_name, post_status, post_modified\n    FROM posts\n    WHERE post_type = 'page'\n    ORDER BY post_modified DESC\n    LIMIT 7\n");
$recentPagesStmt->execute();
$recentPages = $recentPagesStmt->fetchAll();

$recentMediaStmt = $link->prepare("\n    SELECT ID, post_title, guid, post_date\n    FROM posts\n    WHERE post_type = 'attachment'\n    ORDER BY ID DESC\n    LIMIT 5\n");
$recentMediaStmt->execute();
$recentMedia = $recentMediaStmt->fetchAll();

$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
$currentPath = '/' . implode('/', array_filter($url));
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <style>
        :root {
            --bg: #eef3f9;
            --card: #ffffff;
            --line: #d9e2ef;
            --title: #0f172a;
            --text: #4b5563;
            --brand: #0f766e;
            --brand-2: #115e59;
            --danger: #b91c1c;
            --sidebar: #101827;
            --sidebar-soft: #1f2937;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at 10% 0%, #f8fbff 0%, var(--bg) 55%);
            color: var(--title);
        }

        .layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #0f172a 0%, var(--sidebar) 80%);
            color: #e5e7eb;
            padding: 20px 14px;
            border-right: 1px solid #1f2937;
        }

        .brand {
            margin: 0 8px 18px;
            padding: 14px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
        }

        .brand h1 {
            margin: 0;
            font-size: 18px;
            color: #fff;
        }

        .brand p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #93c5fd;
        }

        .nav {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav li {
            margin: 4px 0;
        }

        .nav a {
            display: block;
            color: #c7d2fe;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid transparent;
        }

        .nav a:hover,
        .nav a.active {
            background: var(--sidebar-soft);
            border-color: rgba(255,255,255,0.08);
            color: #fff;
        }

        .main {
            padding: 20px;
        }

        .topbar {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 24px;
        }

        .topbar p {
            margin: 4px 0 0;
            color: var(--text);
            font-size: 14px;
        }

        .btn {
            border-radius: 10px;
            padding: 9px 13px;
            border: 1px solid transparent;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
        }

        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-2); }

        .btn-ghost {
            background: #fff;
            color: #1f2937;
            border-color: var(--line);
        }

        .btn-danger {
            background: #fff;
            color: var(--danger);
            border-color: #fecaca;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .stat {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
        }

        .stat small {
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .08em;
        }

        .stat strong {
            display: block;
            font-size: 30px;
            line-height: 1;
            margin-top: 8px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 14px;
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        .panel h3 {
            margin: 0;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            font-size: 16px;
        }

        .list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .list li {
            border-top: 1px solid #eef2f7;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .list li:first-child { border-top: 0; }

        .meta {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
        }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            text-transform: lowercase;
            margin-left: 6px;
        }

        .status-publish { background: #dcfce7; color: #166534; }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-private { background: #e0e7ff; color: #3730a3; }
        .status-trash { background: #fee2e2; color: #991b1b; }

        .quick-links {
            padding: 14px 16px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .quick-links a {
            display: block;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--line);
            color: #0f172a;
            text-decoration: none;
            font-weight: 600;
            background: #fff;
        }

        .quick-links a:hover {
            border-color: #9fb4cf;
            background: #f8fbff;
        }

        @media (max-width: 1100px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { padding: 14px; }
            .nav { display: flex; gap: 6px; flex-wrap: wrap; }
            .nav li { margin: 0; }
            .content-grid { grid-template-columns: 1fr; }
            .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 620px) {
            .topbar { flex-direction: column; align-items: flex-start; }
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>


<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <h1>Fundamental CMS</h1>
            <p>Admin Panel</p>
        </div>

        <ul class="nav">
            <li><a href="/dashboard" class="<?php echo $currentPath === '/dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="/dashboard/pages" class="<?php echo str_starts_with($currentPath, '/dashboard/pages') ? 'active' : ''; ?>">Pagina's</a></li>
            <li><a href="/dashboard/media" class="<?php echo str_starts_with($currentPath, '/dashboard/media') ? 'active' : ''; ?>">Media Library</a></li>
            <li><a href="/dashboard/menus" class="<?php echo str_starts_with($currentPath, '/dashboard/menus') ? 'active' : ''; ?>">Menu Beheer</a></li>
            <li><a href="/dashboard/contact" class="<?php echo str_starts_with($currentPath, '/dashboard/contact') ? 'active' : ''; ?>">Contact Berichten</a></li>
            <li><a href="/dashboard?logout=1">Uitloggen</a></li>
        </ul>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h2>Dashboard</h2>
                <p>Welkom terug, <?php echo esc_html($username); ?>. Hier is je content-overzicht.</p>
            </div>
            <div>
                <a class="btn btn-primary" href="/dashboard/pages">Ga Naar Pagina's</a>
                <a class="btn btn-ghost" href="/dashboard/media">Upload Media</a>
                <a class="btn btn-danger" href="/dashboard?logout=1">Logout</a>
            </div>
        </div>

        <section class="stat-grid">
            <article class="stat"><small>Pagina's</small><strong><?php echo $stats['pages']; ?></strong></article>
            <article class="stat"><small>Gepubliceerd</small><strong><?php echo $stats['published_pages']; ?></strong></article>
            <article class="stat"><small>Concepten</small><strong><?php echo $stats['draft_pages']; ?></strong></article>
            <article class="stat"><small>Media</small><strong><?php echo $stats['media']; ?></strong></article>
            <article class="stat"><small>Gebruikers</small><strong><?php echo $stats['users']; ?></strong></article>
        </section>

        <section class="content-grid">
            <article class="panel">
                <h3>Recent Gewijzigde Pagina's</h3>
                <?php if (empty($recentPages)): ?>
                    <p style="padding:14px 16px; color:#64748b;">Nog geen pagina's gevonden.</p>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach ($recentPages as $page): ?>
                            <li>
                                <div>
                                    <strong><?php echo esc_html((string) ($page['post_title'] ?: '(Zonder titel)')); ?></strong>
                                    <span class="status status-<?php echo esc_attr((string) $page['post_status']); ?>"><?php echo esc_html((string) $page['post_status']); ?></span>
                                    <div class="meta">/<?php echo esc_html((string) $page['post_name']); ?> | <?php echo esc_html((string) $page['post_modified']); ?></div>
                                </div>
                                <a class="btn btn-ghost" href="/dashboard/pages/edit?id=<?php echo (int) $page['ID']; ?>">Bewerken</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>

            <section style="display:grid; gap:14px;">
                <article class="panel">
                    <h3>Snelle Acties</h3>
                    <div class="quick-links">
                        <a href="/dashboard/pages">Pagina Overzicht</a>
                        <a href="/dashboard/pages/create">Nieuwe Pagina</a>
                        <a href="/dashboard/pages/edit?id=1">Homepage Bewerken</a>
                        <a href="/dashboard/media">Media Uploaden</a>
                        <a href="/dashboard/menus">Header/Footer Menu's</a>
                        <a href="/dashboard/contact">Contact Berichten</a>
                    </div>
                </article>

                <article class="panel">
                    <h3>Laatste Media</h3>
                    <?php if (empty($recentMedia)): ?>
                        <p style="padding:14px 16px; color:#64748b;">Nog geen media geupload.</p>
                    <?php else: ?>
                        <ul class="list">
                            <?php foreach ($recentMedia as $media): ?>
                                <li>
                                    <div>
                                        <strong><?php echo esc_html((string) ($media['post_title'] ?: '(Media)')); ?></strong>
                                        <div class="meta"><?php echo esc_html((string) $media['post_date']); ?></div>
                                    </div>
                                    <a class="btn btn-ghost" href="<?php echo esc_url((string) ($media['guid'] ?: '#')); ?>" target="_blank" rel="noopener">Open</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>
        </section>
    </main>
</div>
</body>
</html>
