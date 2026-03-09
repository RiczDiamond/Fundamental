<?php

if (!is_user_logged_in()) {
    mol_safe_redirect('/login');
}

function menu_decode(string $json): array {
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $items[] = [
            'id' => $id,
            'label' => trim((string) ($item['label'] ?? '')),
            'url' => trim((string) ($item['url'] ?? '')),
            'parent_id' => trim((string) ($item['parent_id'] ?? '')),
        ];
    }

    return $items;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mol_require_valid_nonce('menus_save')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $headerJson = (string) mol_unslash($_POST['menu_json_header'] ?? '[]');
    $footerJson = (string) mol_unslash($_POST['menu_json_footer'] ?? '[]');

    if ($error === '') {
        $headerItems = menu_decode($headerJson);
        $footerItems = menu_decode($footerJson);

        upsert_option_value($link, 'dashboard_menu_header', json_encode($headerItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        upsert_option_value($link, 'dashboard_menu_footer', json_encode($footerItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

        $message = 'Menu\'s opgeslagen.';
    }
}

$headerMenu = menu_decode((string) get_option_value($link, 'dashboard_menu_header', '[]'));
$footerMenu = menu_decode((string) get_option_value($link, 'dashboard_menu_footer', '[]'));
$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu beheer</title>
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
        .head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        .head h1 { margin:0; font-size:24px; }
        .btn { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:9px 12px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        .notice,.error { border-radius:8px; padding:10px 12px; margin-bottom:10px; font-size:14px; }
        .notice { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .cols { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        .menu-box { border:1px solid var(--line); border-radius:10px; padding:10px; background:#f8fafc; }
        .menu-box h2 { margin:0 0 10px; font-size:18px; }
        .items { list-style:none; margin:0; padding:0; min-height:80px; }
        .item { border:1px solid #dbe3ef; background:#fff; border-radius:8px; padding:10px; margin-bottom:8px; cursor:grab; }
        .item.dragging { opacity:.6; }
        input,select { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:8px; font:inherit; }
        .row { display:grid; grid-template-columns: 1fr 1fr 180px auto; gap:8px; margin-top:6px; }
        .toolbar { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
        .danger { color:#b91c1c; border-color:#fecaca; }
        @media (max-width: 980px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom:8px; }
        }
        @media (max-width: 900px) { .cols { grid-template-columns:1fr; } .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <h2>Fundamental CMS</h2>
        <a href="/dashboard">Dashboard</a>
        <a href="/dashboard/pages">Pagina's</a>
        <a href="/dashboard/media">Media Library</a>
        <a class="active" href="/dashboard/menus">Menu Beheer</a>
        <a href="/dashboard/contact">Contact Berichten</a>
        <a href="/dashboard/logout">Uitloggen</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <strong>Menu Beheer</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
<div class="wrap">
    <div class="panel">
        <div class="head">
            <h1>Menu beheer</h1>
            <div>
                <a class="btn btn-ghost" href="/dashboard/pages">Pagina's</a>
                <a class="btn btn-ghost" href="/dashboard/media">Media</a>
                <a class="btn btn-ghost" href="/dashboard">Dashboard</a>
            </div>
        </div>
        <?php if ($message !== ''): ?><div class="notice"><?php echo esc_html($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="error"><?php echo esc_html($error); ?></div><?php endif; ?>

        <form method="post" action="/dashboard/menus" id="menu-form">
            <?php mol_nonce_field('menus_save'); ?>
            <input type="hidden" name="menu_json_header" id="menu_json_header" value="[]">
            <input type="hidden" name="menu_json_footer" id="menu_json_footer" value="[]">

            <div class="cols">
                <section class="menu-box">
                    <h2>Header menu</h2>
                    <ul class="items" id="menu-header" data-location="header"></ul>
                    <div class="toolbar">
                        <button class="btn btn-ghost" type="button" onclick="addMenuItem('header')">+ Item</button>
                    </div>
                </section>

                <section class="menu-box">
                    <h2>Footer menu</h2>
                    <ul class="items" id="menu-footer" data-location="footer"></ul>
                    <div class="toolbar">
                        <button class="btn btn-ghost" type="button" onclick="addMenuItem('footer')">+ Item</button>
                    </div>
                </section>
            </div>

            <div class="toolbar" style="margin-top:14px;">
                <button class="btn" type="submit">Menu's opslaan</button>
            </div>
        </form>
    </div>
</div>
        </div>
    </main>
</div>

<template id="menu-item-template">
    <li class="item" draggable="true">
        <div class="row">
            <input type="text" data-key="label" placeholder="Label">
            <input type="text" data-key="url" placeholder="URL, bijv. /about/">
            <select data-key="parent_id"></select>
            <button class="btn btn-ghost danger" type="button" onclick="removeMenuItem(this)">Verwijder</button>
        </div>
    </li>
</template>

<script>
var initialMenus = {
    header: <?php echo json_encode($headerMenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    footer: <?php echo json_encode($footerMenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};

function uid() {
    return 'm-' + Math.random().toString(36).slice(2, 10);
}

function makeItem(location, itemData) {
    var template = document.getElementById('menu-item-template');
    var node = template.content.firstElementChild.cloneNode(true);
    var data = itemData || {};

    node.dataset.id = data.id || uid();
    node.querySelector('[data-key="label"]').value = data.label || '';
    node.querySelector('[data-key="url"]').value = data.url || '';
    node.querySelector('[data-key="parent_id"]').value = data.parent_id || '';

    node.addEventListener('dragstart', function () {
        node.classList.add('dragging');
    });
    node.addEventListener('dragend', function () {
        node.classList.remove('dragging');
    });

    return node;
}

function refreshParentOptions(listEl) {
    var items = Array.from(listEl.querySelectorAll('.item'));
    var options = [{ id: '', label: 'Geen parent (top-level)' }];

    items.forEach(function (item, idx) {
        var labelInput = item.querySelector('[data-key="label"]');
        var label = labelInput ? labelInput.value.trim() : '';
        options.push({ id: item.dataset.id, label: (idx + 1) + '. ' + (label || 'Onbenoemd item') });
    });

    items.forEach(function (item) {
        var select = item.querySelector('[data-key="parent_id"]');
        if (!select) return;

        var current = select.value;
        select.innerHTML = '';

        options.forEach(function (opt) {
            if (opt.id === item.dataset.id) return;
            var o = document.createElement('option');
            o.value = opt.id;
            o.textContent = opt.label;
            select.appendChild(o);
        });

        if (Array.from(select.options).some(function (o) { return o.value === current; })) {
            select.value = current;
        }
    });
}

function addMenuItem(location, data) {
    var list = document.getElementById('menu-' + location);
    var item = makeItem(location, data || {});
    list.appendChild(item);
    refreshParentOptions(list);
}

function removeMenuItem(button) {
    var item = button.closest('.item');
    if (!item) return;
    var list = item.closest('.items');
    item.remove();
    if (list) refreshParentOptions(list);
}

function serializeMenu(location) {
    var list = document.getElementById('menu-' + location);
    var result = [];

    list.querySelectorAll('.item').forEach(function (item) {
        result.push({
            id: item.dataset.id || uid(),
            label: (item.querySelector('[data-key="label"]').value || '').trim(),
            url: (item.querySelector('[data-key="url"]').value || '').trim(),
            parent_id: (item.querySelector('[data-key="parent_id"]').value || '').trim()
        });
    });

    return result;
}

function setupDrag(listEl) {
    listEl.addEventListener('dragover', function (event) {
        event.preventDefault();
        var dragging = listEl.querySelector('.item.dragging');
        if (!dragging) return;

        var afterElement = Array.from(listEl.querySelectorAll('.item:not(.dragging)')).find(function (el) {
            var rect = el.getBoundingClientRect();
            return event.clientY < rect.top + rect.height / 2;
        });

        if (!afterElement) {
            listEl.appendChild(dragging);
        } else {
            listEl.insertBefore(dragging, afterElement);
        }
    });

    listEl.addEventListener('drop', function () {
        refreshParentOptions(listEl);
    });
}

['header', 'footer'].forEach(function (location) {
    var list = document.getElementById('menu-' + location);
    setupDrag(list);

    (initialMenus[location] || []).forEach(function (item) {
        addMenuItem(location, item);
    });

    if ((initialMenus[location] || []).length === 0) {
        addMenuItem(location, {});
    }

    list.addEventListener('input', function () {
        refreshParentOptions(list);
    });
});

document.getElementById('menu-form').addEventListener('submit', function () {
    document.getElementById('menu_json_header').value = JSON.stringify(serializeMenu('header'));
    document.getElementById('menu_json_footer').value = JSON.stringify(serializeMenu('footer'));
});
</script>
</body>
</html>
