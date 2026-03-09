<?php

if (!is_user_logged_in()) {
    mol_safe_redirect('/login');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $intent = sanitize_text_field($_POST['intent'] ?? '');

    if ($intent === 'quick_edit') {
        if (!mol_require_valid_nonce('pages_quick_edit')) {
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        }

        $id = absint($_POST['id'] ?? 0);
        $title = sanitize_text_field($_POST['post_title'] ?? '');
        $slugInput = sanitize_text_field($_POST['post_name'] ?? '');
        $status = sanitize_text_field($_POST['post_status'] ?? 'draft');
        $allowedStatus = ['draft', 'publish', 'private', 'trash'];

        if (!in_array($status, $allowedStatus, true)) {
            $status = 'draft';
        }

        if ($error === '' && ($id < 1 || $title === '')) {
            $error = 'Quick edit vereist een geldige pagina en titel.';
        } elseif ($error === '') {
            $slug = sanitize_title($slugInput !== '' ? $slugInput : $title);
            if ($slug === '') {
                $slug = 'pagina-' . $id;
            }

            $exists = $link->prepare("\n                SELECT ID\n                FROM posts\n                WHERE post_type = 'page'\n                  AND post_name = :slug\n                  AND ID <> :id\n                LIMIT 1\n            ");
            $exists->execute(['slug' => $slug, 'id' => $id]);

            if ($exists->fetch()) {
                $slug .= '-' . $id;
            }

            $update = $link->prepare("\n                UPDATE posts\n                SET post_title = :title,\n                    post_name = :slug,\n                    post_status = :status,\n                    post_modified = NOW()\n                WHERE ID = :id\n                  AND post_type = 'page'\n                LIMIT 1\n            ");
            $update->execute([
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'id' => $id,
            ]);

            $message = 'Quick edit opgeslagen.';
        }
    }

    if ($intent === 'bulk') {
        if (!mol_require_valid_nonce('pages_bulk')) {
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        }

        $bulkAction = sanitize_text_field($_POST['bulk_action'] ?? '');
        $idsRaw = $_POST['ids'] ?? [];
        $ids = [];

        if (is_array($idsRaw)) {
            foreach ($idsRaw as $idRaw) {
                $id = absint($idRaw);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        if ($error === '' && $ids === []) {
            $error = 'Selecteer minimaal een pagina voor bulk-acties.';
        } elseif ($error === '') {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            if ($bulkAction === 'delete') {
                $del = $link->prepare("DELETE FROM posts WHERE post_type = 'page' AND ID IN ($placeholders)");
                $del->execute($ids);
                $message = 'Pagina\'s permanent verwijderd.';
            } else {
                $statusMap = [
                    'trash' => 'trash',
                    'publish' => 'publish',
                    'draft' => 'draft',
                    'private' => 'private',
                ];

                if (!isset($statusMap[$bulkAction])) {
                    $error = 'Onbekende bulk-actie.';
                } else {
                    $status = $statusMap[$bulkAction];
                    $sql = "UPDATE posts SET post_status = ?, post_modified = NOW() WHERE post_type = 'page' AND ID IN ($placeholders)";
                    $stmt = $link->prepare($sql);
                    $stmt->execute(array_merge([$status], $ids));
                    $message = 'Bulk-actie uitgevoerd.';
                }
            }
        }
    }
}

$q = sanitize_text_field($_GET['q'] ?? '');
$statusFilter = sanitize_text_field($_GET['status'] ?? 'all');
$allowedFilters = ['all', 'publish', 'draft', 'private', 'trash'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$sql = "\n    SELECT ID, post_title, post_name, post_status, post_date, post_modified\n    FROM posts\n    WHERE post_type = 'page'\n";
$bind = [];

if ($statusFilter !== 'all') {
    $sql .= " AND post_status = :status ";
    $bind['status'] = $statusFilter;
}

if ($q !== '') {
    $sql .= " AND (post_title LIKE :q OR post_name LIKE :q) ";
    $bind['q'] = '%' . $q . '%';
}

$sql .= " ORDER BY post_modified DESC ";

$stmt = $link->prepare($sql);
$stmt->execute($bind);
$pages = $stmt->fetchAll();
$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagina's beheren</title>
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --line:#e2e8f0; --text:#334155; --accent:#0f766e; --danger:#b91c1c; }
        * { box-sizing: border-box; }
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
        .wrap { max-width: 1200px; margin: 22px auto; padding: 0 14px; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:12px; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        .head h1 { margin:0; font-size:24px; }
        .btn { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:9px 12px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        .btn-danger { background:#fff; color:var(--danger); border:1px solid #fecaca; }
        .notice,.error { padding:10px 12px; border-radius:8px; margin-bottom:10px; font-size:14px; }
        .notice { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; }
        .filters { display:grid; grid-template-columns: 1fr 180px 120px; gap:10px; margin-top:12px; }
        input,select { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font:inherit; }
        table { width:100%; border-collapse: collapse; }
        th,td { border-top:1px solid #eef2f7; padding:10px; text-align:left; vertical-align: top; font-size:14px; }
        th { color:#475569; font-size:12px; text-transform: uppercase; letter-spacing: .06em; }
        .status { display:inline-block; border-radius:999px; padding:2px 8px; font-size:12px; }
        .status-publish { background:#dcfce7; color:#166534; }
        .status-draft { background:#fef3c7; color:#92400e; }
        .status-private { background:#e0e7ff; color:#3730a3; }
        .status-trash { background:#fee2e2; color:#991b1b; }
        .actions a, .actions button { margin-right:6px; margin-top:4px; }
        .quick { display:none; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:10px; margin-top:8px; }
        .quick.show { display:block; }
        .bulkbar { display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap; }
        @media (max-width: 980px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom:8px; }
        }
        @media (max-width: 800px) { .filters { grid-template-columns: 1fr; } table { font-size:13px; } }
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
        <a href="/dashboard/logout">Uitloggen</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <strong>Pagina Overzicht</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
<div class="wrap">
    <div class="panel">
        <div class="head">
            <h1>Pagina's</h1>
            <div>
                <a class="btn" href="/dashboard/pages/create">+ Nieuwe pagina</a>
                <a class="btn btn-ghost" href="/dashboard/media">Media</a>
                <a class="btn btn-ghost" href="/dashboard/menus">Menu's</a>
                <a class="btn btn-ghost" href="/dashboard">Dashboard</a>
            </div>
        </div>

        <?php if ($message !== ''): ?><div class="notice"><?php echo esc_html($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="error"><?php echo esc_html($error); ?></div><?php endif; ?>

        <form method="get" action="/dashboard/pages" class="filters">
            <input type="text" name="q" placeholder="Zoek op titel of slug" value="<?php echo esc_attr($q); ?>">
            <select name="status">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Alle statussen</option>
                <option value="publish" <?php echo $statusFilter === 'publish' ? 'selected' : ''; ?>>Publish</option>
                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="private" <?php echo $statusFilter === 'private' ? 'selected' : ''; ?>>Private</option>
                <option value="trash" <?php echo $statusFilter === 'trash' ? 'selected' : ''; ?>>Prullenbak</option>
            </select>
            <button class="btn" type="submit">Filter</button>
        </form>
    </div>

    <div class="panel">
        <form method="post" action="/dashboard/pages<?php echo $q !== '' || $statusFilter !== 'all' ? '?q=' . urlencode($q) . '&status=' . urlencode($statusFilter) : ''; ?>">
            <input type="hidden" name="intent" value="bulk">
            <?php mol_nonce_field('pages_bulk'); ?>
            <div class="bulkbar">
                <select name="bulk_action" style="max-width:220px;">
                    <option value="">Bulk-actie</option>
                    <option value="publish">Publiceren</option>
                    <option value="draft">Naar concept</option>
                    <option value="private">Naar private</option>
                    <option value="trash">Naar prullenbak</option>
                    <option value="delete">Permanent verwijderen</option>
                </select>
                <button class="btn" type="submit">Toepassen</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="check-all"></th>
                        <th>Titel</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Datum</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pages)): ?>
                    <tr><td colspan="6">Geen pagina's gevonden.</td></tr>
                <?php else: ?>
                    <?php foreach ($pages as $page): ?>
                        <?php $id = (int) $page['ID']; ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo $id; ?>"></td>
                            <td>
                                <strong><?php echo esc_html((string) ($page['post_title'] ?: '(Zonder titel)')); ?></strong>
                                <div class="quick" id="quick-<?php echo $id; ?>">
                                    <label>Titel</label>
                                    <input type="text" id="quick-title-<?php echo $id; ?>" value="<?php echo esc_attr((string) $page['post_title']); ?>">
                                    <label>Slug</label>
                                    <input type="text" id="quick-slug-<?php echo $id; ?>" value="<?php echo esc_attr((string) $page['post_name']); ?>">
                                    <label>Status</label>
                                    <select id="quick-status-<?php echo $id; ?>">
                                        <?php foreach (['publish','draft','private','trash'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $s === (string) $page['post_status'] ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div style="margin-top:8px;">
                                        <button class="btn" type="button" onclick="submitQuick(<?php echo $id; ?>)">Opslaan</button>
                                        <button class="btn btn-ghost" type="button" onclick="toggleQuick(<?php echo $id; ?>)">Sluiten</button>
                                    </div>
                                </div>
                            </td>
                            <td>/<?php echo esc_html((string) $page['post_name']); ?></td>
                            <td><span class="status status-<?php echo esc_attr((string) $page['post_status']); ?>"><?php echo esc_html((string) $page['post_status']); ?></span></td>
                            <td><?php echo esc_html((string) $page['post_modified']); ?></td>
                            <td class="actions">
                                <a class="btn btn-ghost" href="/dashboard/pages/edit?id=<?php echo $id; ?>">Bewerken</a>
                                <button class="btn btn-ghost" type="button" onclick="toggleQuick(<?php echo $id; ?>)">Quick edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>
        </div>
    </main>
</div>
<script>
function toggleQuick(id) {
    var el = document.getElementById('quick-' + id);
    if (!el) return;
    el.classList.toggle('show');
}
var checkAll = document.getElementById('check-all');
if (checkAll) {
    checkAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
            cb.checked = checkAll.checked;
        });
    });
}

function submitQuick(id) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '/dashboard/pages';

    var data = {
        intent: 'quick_edit',
        _wpnonce: '<?php echo esc_attr(mol_create_nonce('pages_quick_edit')); ?>',
        id: String(id),
        post_title: document.getElementById('quick-title-' + id).value,
        post_name: document.getElementById('quick-slug-' + id).value,
        post_status: document.getElementById('quick-status-' + id).value
    };

    Object.keys(data).forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
