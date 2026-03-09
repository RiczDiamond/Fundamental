<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    wp_safe_redirect('/login');
}

$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
$message = '';
$error = '';

$form = [
    'post_title' => '',
    'post_name' => '',
    'post_content' => '',
    'post_status' => 'draft',
    'with_starter_sections' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wp_require_valid_nonce('pages_create')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $form['post_title'] = sanitize_text_field($_POST['post_title'] ?? '');
    $form['post_name'] = sanitize_text_field($_POST['post_name'] ?? '');
    $form['post_content'] = sanitize_textarea_field($_POST['post_content'] ?? '');
    $form['post_status'] = sanitize_text_field($_POST['post_status'] ?? 'draft');
    $form['with_starter_sections'] = isset($_POST['with_starter_sections']) ? '1' : '0';

    $allowedStatus = ['draft', 'publish', 'private'];
    if (!in_array($form['post_status'], $allowedStatus, true)) {
        $form['post_status'] = 'draft';
    }

    if ($form['post_title'] === '') {
        $error = 'Titel is verplicht.';
    }

    if ($error === '') {
        $slug = sanitize_title($form['post_name'] !== '' ? $form['post_name'] : $form['post_title']);

        if ($slug === '') {
            $slug = 'nieuwe-pagina';
        }

        $baseSlug = $slug;
        $counter = 2;

        while (true) {
            $existsStmt = $link->prepare("\n                SELECT ID\n                FROM posts\n                WHERE post_type = 'page'\n                  AND post_name = :slug\n                LIMIT 1\n            ");
            $existsStmt->execute(['slug' => $slug]);

            if (!$existsStmt->fetch()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $guid = '/' . $slug;

        $insertStmt = $link->prepare("\n            INSERT INTO posts (\n                post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,\n                post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged,\n                post_modified, post_modified_gmt, post_content_filtered, post_parent, guid,\n                menu_order, post_type, post_mime_type, comment_count\n            ) VALUES (\n                :post_author, NOW(), NOW(), :post_content, :post_title, '',\n                :post_status, 'closed', 'closed', '', :post_name, '', '',\n                NOW(), NOW(), '', 0, :guid,\n                0, 'page', '', 0\n            )\n        ");

        $insertStmt->execute([
            'post_author' => $userId,
            'post_content' => $form['post_content'],
            'post_title' => $form['post_title'],
            'post_status' => $form['post_status'],
            'post_name' => $slug,
            'guid' => $guid,
        ]);

        $newPageId = (int) $link->lastInsertId();

        if ($newPageId > 0) {
            $sectionsPayload = [];

            if ($form['with_starter_sections'] === '1') {
                $sectionsPayload = [
                    [
                        'type' => 'hero',
                        'fields' => [
                            'headline' => $form['post_title'] !== '' ? $form['post_title'] : 'Nieuwe pagina',
                            'subline' => 'Korte introductie van deze pagina',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'fields' => [
                            'title' => 'Inhoud',
                            'content' => $form['post_content'] !== '' ? $form['post_content'] : 'Start hier met de inhoud van je pagina.',
                        ],
                    ],
                ];
            }

            $sectionsJson = json_encode($sectionsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($sectionsJson)) {
                $sectionsJson = '[]';
            }

            upsert_post_meta($link, $newPageId, '_sections_json', $sectionsJson);
            wp_safe_redirect('/dashboard/pages/edit?id=' . $newPageId . '&created=1');
        }

        $error = 'Aanmaken van de pagina is mislukt.';
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nieuwe pagina</title>
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --line:#e2e8f0; --text:#334155; --accent:#0f766e; --danger:#b91c1c; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:"Segoe UI", Tahoma, sans-serif; }
        .admin-layout { display:grid; grid-template-columns:250px 1fr; min-height:100vh; }
        .sidebar { background:#0f172a; color:#cbd5e1; padding:20px 14px; }
        .sidebar h2 { margin:4px 10px 14px; font-size:18px; color:#fff; }
        .sidebar a { display:block; color:#cbd5e1; text-decoration:none; padding:10px 12px; border-radius:8px; margin-bottom:4px; }
        .sidebar a:hover, .sidebar a.active { background:#1e293b; color:#fff; }
        .main { min-width:0; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .topbar strong { font-size:16px; }
        .content { padding:16px; }
        .wrap { max-width:900px; margin:22px auto; padding:0 14px; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px; }
        h1 { margin:0 0 12px; font-size:24px; }
        label { display:block; margin:12px 0 6px; font-weight:600; font-size:14px; }
        input, textarea, select { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font:inherit; color:inherit; background:#fff; }
        textarea { min-height:140px; resize:vertical; }
        .row { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .btn { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:10px 14px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:var(--danger); border-radius:8px; padding:10px 12px; margin-bottom:10px; font-size:14px; }
        .hint { margin-top:8px; color:#64748b; font-size:12px; }
        @media (max-width:980px) { .admin-layout { grid-template-columns:1fr; } .sidebar { padding-bottom:8px; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <h2>Fundamental CMS</h2>
        <a href="/dashboard">Dashboard</a>
        <a class="active" href="/dashboard/pages">Pagina's</a>
        <a href="/dashboard/media">Media Library</a>
        <a href="/dashboard/menus">Menu Beheer</a>
        <a href="/dashboard/contact">Contact Berichten</a>
        <a href="/dashboard?logout=1">Uitloggen</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <strong>Nieuwe Pagina</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
            <div class="wrap">
                <div class="panel">
                    <h1>Nieuwe pagina aanmaken</h1>

                    <?php if ($error !== ''): ?>
                        <div class="error"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>

                    <form method="post" action="/dashboard/pages/create">
                        <?php wp_nonce_field('pages_create'); ?>
                        <label for="post_title">Titel</label>
                        <input id="post_title" name="post_title" type="text" value="<?php echo esc_attr($form['post_title']); ?>" required>

                        <label for="post_name">Slug (optioneel)</label>
                        <input id="post_name" name="post_name" type="text" value="<?php echo esc_attr($form['post_name']); ?>" placeholder="bijv. over-ons">

                        <label for="post_content">Inhoud</label>
                        <textarea id="post_content" name="post_content"><?php echo esc_textarea($form['post_content']); ?></textarea>

                        <label for="post_status">Status</label>
                        <select id="post_status" name="post_status">
                            <option value="draft" <?php echo $form['post_status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="publish" <?php echo $form['post_status'] === 'publish' ? 'selected' : ''; ?>>Publish</option>
                            <option value="private" <?php echo $form['post_status'] === 'private' ? 'selected' : ''; ?>>Private</option>
                        </select>

                        <label style="display:flex; align-items:center; gap:8px; font-weight:500; margin-top:14px;">
                            <input type="checkbox" name="with_starter_sections" value="1" <?php echo $form['with_starter_sections'] === '1' ? 'checked' : ''; ?> style="width:auto;">
                            Starter sections toevoegen (Hero + Text)
                        </label>

                        <p class="hint">Na aanmaken ga je automatisch naar de edit-pagina om sections toe te voegen.</p>

                        <div class="row">
                            <a class="btn btn-ghost" href="/dashboard/pages">Terug naar overzicht</a>
                            <button class="btn" type="submit">Pagina aanmaken</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
