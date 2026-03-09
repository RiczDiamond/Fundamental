<?php

if (!is_user_logged_in()) {
    mol_safe_redirect('/login');
}

$message = '';
$error = '';

// handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sanitize_text_field($_POST['intent'] ?? '') === 'delete') {
    if (!mol_require_valid_nonce('media_delete')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $delId = absint($_POST['id'] ?? 0);
        if ($delId < 1) {
            $error = 'Ongeldig ID.';
        } else {
            // load attached file path
            $stmt = $link->prepare("SELECT guid FROM posts WHERE ID = :id AND post_type = 'attachment' LIMIT 1");
            $stmt->execute(['id'=>$delId]);
            $guid = $stmt->fetchColumn();
            if ($guid) {
                // remove file from disk if possible
                $meta = get_post_meta($link, $delId, '_mol_attached_file');
                if (is_string($meta) && $meta !== '') {
                    $file = __DIR__ . '/../public/' . ltrim($meta, '/');
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                // delete post and meta
                $del = $link->prepare("DELETE FROM postmeta WHERE post_id = :id");
                $del->execute(['id'=>$delId]);
                $del2 = $link->prepare("DELETE FROM posts WHERE ID = :id AND post_type = 'attachment' LIMIT 1");
                $del2->execute(['id'=>$delId]);
                $message = 'Media verwijderd.';
            } else {
                $error = 'Media niet gevonden.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sanitize_text_field($_POST['intent'] ?? '') === 'upload') {
    if (!mol_require_valid_nonce('media_upload')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    if ($error === '') {
    if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
        $error = 'Geen bestand ontvangen.';
    } else {
        $file = $_FILES['media_file'];
        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        $errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);

        if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || $name === '') {
            $error = 'Upload mislukt.';
        } elseif ($size <= 0 || $size > 15 * 1024 * 1024) {
            $error = 'Bestand is leeg of te groot (max 15MB).';
        } else {
            $safeBase = preg_replace('/[^a-z0-9\._-]/i', '-', strtolower($name)) ?? 'file';
            $safeBase = trim($safeBase, '-.');
            if ($safeBase === '') {
                $safeBase = 'file';
            }

            $uploadsAbs = __DIR__ . '/../public/uploads';
            if (!is_dir($uploadsAbs)) {
                mkdir($uploadsAbs, 0775, true);
            }

            $dotPos = strrpos($safeBase, '.');
            $ext = $dotPos !== false ? substr($safeBase, $dotPos) : '';
            $basename = $dotPos !== false ? substr($safeBase, 0, $dotPos) : $safeBase;
            $targetName = $safeBase;
            $counter = 1;

            while (is_file($uploadsAbs . DIRECTORY_SEPARATOR . $targetName)) {
                $targetName = $basename . '-' . $counter . $ext;
                $counter++;
            }

            $targetAbs = $uploadsAbs . DIRECTORY_SEPARATOR . $targetName;
            $targetRel = 'uploads/' . $targetName;
            $targetUrl = '/uploads/' . $targetName;

            if (!move_uploaded_file($tmp, $targetAbs)) {
                $error = 'Kon bestand niet opslaan.';
            } else {
                $mime = mime_content_type($targetAbs);
                if (!is_string($mime) || $mime === '') {
                    $mime = 'application/octet-stream';
                }

                $userId = (int) ($_SESSION['user_id'] ?? 0);
                $title = pathinfo($targetName, PATHINFO_FILENAME);

                $insert = $link->prepare("\n                    INSERT INTO posts (\n                        post_author, post_content, post_title, post_excerpt, post_status, post_name,\n                        to_ping, pinged, post_content_filtered, guid, post_type, post_mime_type\n                    ) VALUES (\n                        :author, '', :title, '', 'inherit', :slug,\n                        '', '', '', :guid, 'attachment', :mime\n                    )\n                ");
                $insert->execute([
                    'author' => $userId,
                    'title' => $title,
                    'slug' => $title,
                    'guid' => $targetUrl,
                    'mime' => $mime,
                ]);

                $attachmentId = (int) $link->lastInsertId();
                upsert_post_meta($link, $attachmentId, '_mol_attached_file', $targetRel);

                $message = 'Bestand geupload naar media library.';
            }
        }
    }
    }
}

$q = sanitize_text_field($_GET['q'] ?? '');
$sql = "\n    SELECT p.ID, p.post_title, p.guid, p.post_mime_type, p.post_date, pm.meta_value AS file_path\n    FROM posts p\n    LEFT JOIN postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_mol_attached_file'\n    WHERE p.post_type = 'attachment'\n";
$bind = [];
if ($q !== '') {
    $sql .= " AND (p.post_title LIKE :q OR pm.meta_value LIKE :q) ";
    $bind['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY p.ID DESC LIMIT 300';
$stmt = $link->prepare($sql);
$stmt->execute($bind);
$mediaItems = $stmt->fetchAll();
$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Media Library</title>
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --line:#e2e8f0; --text:#334155; --accent:#0f766e; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:"Segoe UI", Tahoma, sans-serif; }
        .admin-layout { display:grid; grid-template-columns: 250px 1fr; min-height:100vh; }
        .sidebar { background:#0f172a; color:#cbd5e1; padding:20px 14px; }
        .sidebar h2 { margin:4px 10px 14px; font-size:18px; color:#fff; }
        .sidebar a { display:block; color:#cbd5e1; text-decoration:none; padding:10px 12px; border-radius:8px; margin-bottom:4px; }
        .sidebar a:hover, .sidebar a.active { background:#1e293b; color:#fff; }
        .main { min-width:0; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .topbar strong { font-size:16px; }
        .content { padding:16px; }
        .wrap { max-width:1200px; margin:22px auto; padding:0 14px; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:12px; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        .head h1 { margin:0; font-size:24px; }
        .btn { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:9px 12px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        input[type="text"], input[type="file"] { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font:inherit; }
        .bar { display:grid; grid-template-columns:1fr auto; gap:10px; }
        .notice,.error { border-radius:8px; padding:10px 12px; margin-bottom:10px; font-size:14px; }
        .notice { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; }
        .card { border:1px solid var(--line); border-radius:10px; overflow:hidden; background:#fff; }
        .thumb { height:150px; background:#f8fafc; display:flex; align-items:center; justify-content:center; }
        .thumb img { max-width:100%; max-height:100%; display:block; }
        .meta { padding:10px; font-size:12px; line-height:1.5; }
        .url { font-family:Consolas, monospace; word-break: break-all; background:#f8fafc; padding:6px; border-radius:6px; }
        @media (max-width: 980px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom:8px; }
        }
        @media (max-width: 1000px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <h2>Fundamental CMS</h2>
        <a href="/dashboard">Dashboard</a>
        <a href="/dashboard/pages">Pagina's</a>
        <a class="active" href="/dashboard/media">Media Library</a>
        <a href="/dashboard/menus">Menu Beheer</a>
        <a href="/dashboard/contact">Contact Berichten</a>
        <a href="/dashboard/logout">Uitloggen</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <strong>Media Library</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
<div class="wrap">
    <div class="panel">
        <div class="head">
            <h1>Media Library</h1>
            <div>
                <a class="btn btn-ghost" href="/dashboard/pages">Pagina's</a>
                <a class="btn btn-ghost" href="/dashboard/menus">Menu's</a>
                <a class="btn btn-ghost" href="/dashboard">Dashboard</a>
            </div>
        </div>

        <?php if ($message !== ''): ?><div class="notice"><?php echo esc_html($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="error"><?php echo esc_html($error); ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="/dashboard/media" style="margin-bottom:12px;">
            <input type="hidden" name="intent" value="upload">
            <?php mol_nonce_field('media_upload'); ?>
            <div class="bar">
                <input type="file" name="media_file" accept="image/*,.pdf,.svg,.webp,.jpg,.jpeg,.png,.gif" required>
                <button class="btn" type="submit">Upload</button>
            </div>
        </form>

        <form method="get" action="/dashboard/media" class="bar">
            <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Zoek op bestandsnaam">
            <button class="btn" type="submit">Zoeken</button>
        </form>
    </div>

    <div class="panel">
        <div class="grid">
            <?php if (empty($mediaItems)): ?>
                <p>Geen media gevonden.</p>
            <?php else: ?>
                <?php foreach ($mediaItems as $item): ?>
                    <?php
                        $url = (string) ($item['guid'] ?: '');
                        $title = (string) ($item['post_title'] ?: '(zonder titel)');
                        $mime = (string) ($item['post_mime_type'] ?: '');
                        $isImage = str_starts_with($mime, 'image/');
                    ?>
                    <article class="card">
                        <div class="thumb">
                            <?php if ($isImage): ?>
                                <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($title); ?>">
                            <?php else: ?>
                                <span><?php echo esc_html($mime); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="meta">
                            <strong><?php echo esc_html($title); ?></strong><br>
                            <span><?php echo esc_html($mime); ?></span><br>
                            <span>ID: <?php echo (int) $item['ID']; ?></span>
                            <div class="url"><?php echo esc_html($url); ?></div>
                            <button class="btn btn-ghost" type="button" onclick="copyUrl('<?php echo esc_attr($url); ?>')">Kopieer URL</button>
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Weet je zeker dat je dit bestand wilt verwijderen?');">
                                <?php mol_nonce_field('media_delete'); ?>
                                <input type="hidden" name="intent" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $item['ID']; ?>">
                                <button class="btn btn-ghost" type="submit" style="margin-left:4px;">Verwijderen</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
        </div>
    </main>
</div>
<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).catch(function () {});
}
</script>
</body>
</html>
