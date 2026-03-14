<?php

if (!is_user_logged_in()) {
    mol_safe_redirect('/login');
}

$pageId = absint($_GET['id'] ?? 0);
if ($pageId < 1) {
    http_response_code(400);
    echo '<p>Ongeldige pagina ID.</p>';
    return;
}

$stmt = $link->prepare("\n    SELECT ID, post_title, post_name, post_content, post_status\n    FROM posts\n    WHERE ID = :id AND post_type = 'page'\n    LIMIT 1\n");
$stmt->execute(['id' => $pageId]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    echo '<p>Pagina niet gevonden.</p>';
    return;
}

if (isset($_GET['restore_revision'])) {
    $revisionId = absint($_GET['restore_revision']);
    $restoreNonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
    $revision = null;

    if (mol_require_valid_nonce('pages_restore_revision_' . $pageId, '_wpnonce', $_GET)) {
        $revision = get_post_revision_by_id($link, $revisionId, $pageId);
    }

    if ($revision) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        restore_post_from_revision($link, $revision, $userId);
        mol_safe_redirect('/dashboard/pages/edit?id=' . $pageId . '&restored=1');
    }
}

$availableTypes = get_section_types();
$sections = get_flexible_sections($link, $pageId);
if ($sections === []) {
    $sections = get_fixed_page_sections($link, $pageId);
}
if ($sections === []) {
    $sections = [[
        'type' => 'hero',
        'fields' => ['headline' => 'Nieuwe hero titel', 'subline' => 'Korte toelichting'],
    ]];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mol_require_valid_nonce('pages_edit_' . $pageId)) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $title = sanitize_text_field($_POST['post_title'] ?? '');
    $slugInput = sanitize_text_field($_POST['post_name'] ?? '');
    // determine slug immediately so $slug is always defined
    $slug = sanitize_title($slugInput !== '' ? $slugInput : $title);
    if ($slug === '') {
        $slug = 'pagina-' . $pageId;
    }
    // post_content is no longer editable; ignore whatever is submitted
    $status = sanitize_text_field($_POST['post_status'] ?? 'draft');
    $saveAction = sanitize_text_field($_POST['save_action'] ?? 'save');
    $submittedTypes = $_POST['section_type'] ?? [];
    $submittedFields = $_POST['section_fields'] ?? [];

    $normalizedSections = [];

    if ($title === '') {
        $error = 'Titel is verplicht.';
    }

    $allowedStatus = ['draft', 'publish', 'private', 'trash'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'draft';
    }

    if ($saveAction === 'draft') {
        $status = 'draft';
    }
    if ($saveAction === 'publish') {
        $status = 'publish';
    }
    if ($saveAction === 'private') {
        $status = 'private';
    }

    if (!is_array($submittedTypes)) {
        $submittedTypes = [];
    }
    if (!is_array($submittedFields)) {
        $submittedFields = [];
    }

    if ($error === '') {
        foreach ($submittedTypes as $index => $typeRaw) {
            $type = strtolower(trim((string) $typeRaw));
            $type = preg_replace('/[^a-z0-9\-_]/', '', $type) ?? '';

            if ($type === '' || !is_valid_section_type($type)) {
                continue;
            }

            $fieldInput = $submittedFields[$index] ?? [];
            if (!is_array($fieldInput)) {
                $fieldInput = [];
            }

            $normalizedSections[] = [
                'type' => $type,
                'fields' => section_fields_from_form($type, $fieldInput),
                'attrs' => $sections[$index]['attrs'] ?? [],
            ];
        }

        // ensure slug is unique, loop after sections normalized
        if ($error === '') {
            $baseSlug = $slug;
            $counter = 2;

            while (true) {
                $existsStmt = $link->prepare("\n                SELECT ID\n                FROM posts\n                WHERE post_type = 'page'\n                  AND post_name = :slug\n                  AND ID <> :id\n                LIMIT 1\n            ");
                $existsStmt->execute(['slug' => $slug, 'id' => $pageId]);

                if (!$existsStmt->fetch()) {
                    break;
                }

                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        // after uniqueness check $slug is guaranteed, continue saving

        $sectionsJson = json_encode($normalizedSections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($sectionsJson)) {
            $error = 'Kon sections niet opslaan.';
        } else {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            create_post_revision($link, $page, $userId, 'update');

            $updateStmt = $link->prepare("\n                UPDATE posts\n                SET post_title = :title,\n                    post_name = :slug,\n                    post_status = :status,\n                    post_modified = NOW()\n                WHERE ID = :id\n                  AND post_type = 'page'\n                LIMIT 1\n            ");
            $updateStmt->execute([
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'id' => $pageId,
            ]);

            upsert_post_meta($link, $pageId, '_sections_json', $sectionsJson);

            mol_safe_redirect('/dashboard/pages/edit?id=' . $pageId . '&saved=1');
        }
    }

    $page['post_title'] = $title;
    $page['post_name'] = $slug;
    $page['post_status'] = $status;
    $sections = $normalizedSections;
}

$saved = sanitize_text_field($_GET['saved'] ?? '') === '1';
$restored = sanitize_text_field($_GET['restored'] ?? '') === '1';
$created = sanitize_text_field($_GET['created'] ?? '') === '1';
$nextSectionIndex = count($sections);
$sectionEditorFieldsMap = get_section_editor_fields_map();
$sectionHints = [];
foreach ($availableTypes as $schemaType) {
    $sectionHints[$schemaType] = section_type_hint($schemaType);
}

$revisions = get_post_revisions($link, $pageId, 20);
$compareRevision = null;
if (isset($_GET['compare_revision'])) {
    $compareRevision = get_post_revision_by_id($link, absint($_GET['compare_revision']), $pageId);
}

$mediaPickerStmt = $link->prepare("\n    SELECT guid, post_title\n    FROM posts\n    WHERE post_type = 'attachment'\n    ORDER BY ID DESC\n    LIMIT 200\n");
$mediaPickerStmt->execute();
$mediaPickerItems = $mediaPickerStmt->fetchAll();
$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagina bewerken</title>
    <!-- wysiwyg editor (local TinyMCE copy) -->
    <script src="<?php echo BASE_URL; ?>/js/tinymce/js/tinymce/tinymce.min.js"></script>
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --line:#e2e8f0; --text:#334155; --accent:#0f766e; --danger:#b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); font-family: "Segoe UI", Tahoma, sans-serif; color: var(--text); }
        .admin-layout { display:grid; grid-template-columns: 250px 1fr; min-height:100vh; }
        .sidebar { background:#0f172a; color:#cbd5e1; padding:20px 14px; }
        .sidebar h2 { margin:4px 10px 14px; font-size:18px; color:#fff; }
        .sidebar a { display:block; color:#cbd5e1; text-decoration:none; padding:10px 12px; border-radius:8px; margin-bottom:4px; }
        .sidebar a:hover, .sidebar a.active { background:#1e293b; color:#fff; }
        .main { min-width:0; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .topbar strong { font-size:16px; }
        .content { padding:16px; }
        .wrap { max-width: 980px; margin: 28px auto; padding: 0 14px; }
        .panel { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 14px; }
        .head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        h1 { margin: 0; font-size: 24px; }
        h2 { margin: 0; font-size: 20px; }
        .notice,.error { border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; border: 1px solid; font-size: 14px; }
        .notice { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
        .error { border-color: #fecaca; background: #fef2f2; color: var(--danger); }
        label { display: block; margin: 12px 0 6px; font-weight: 600; font-size: 14px; }
        input,textarea,select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 11px; font: inherit; color: inherit; background: #fff; }
        .portfolio-rows .portfolio-row { display:flex; gap:6px; margin-bottom:4px; }
        .portfolio-rows .portfolio-row input { width: 23%; }

        textarea { min-height: 130px; resize: vertical; }
        .row { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; gap: 10px; flex-wrap: wrap; }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer; text-decoration: none; font-weight: 600; display: inline-block; }
        .btn-link { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-danger { background: #fff; border: 1px solid #fecaca; color: #b91c1c; }
        .hint { margin: 8px 0 0; color: #64748b; font-size: 12px; }
        .section-card { border: 1px solid var(--line); border-radius: 10px; padding: 12px; background: #fbfdff; margin-bottom: 12px; }
        .section-card-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
        .section-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .field-wrap.hidden { display: none; }
        .section-grid .full { grid-column: 1 / -1; }
        table { width:100%; border-collapse: collapse; margin-top:10px; }
        th,td { text-align:left; padding:8px; border-bottom:1px solid #eef2f7; vertical-align: top; }
        @media (max-width: 980px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom:8px; }
        }
        @media (max-width: 760px) { .section-grid { grid-template-columns: 1fr; } }
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
            <strong>Pagina Bewerken</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
<div class="wrap">
    <div class="panel">
        <div class="head">
            <h1>Pagina bewerken</h1>
            <a class="btn btn-link" href="/dashboard/pages">Terug naar pagina's</a>
        </div>

        <?php if ($saved): ?><div class="notice">De pagina en sections zijn opgeslagen.</div><?php endif; ?>
        <?php if ($created): ?><div class="notice">Pagina aangemaakt. Je kunt nu direct sections aanpassen.</div><?php endif; ?>
        <?php if ($restored): ?><div class="notice">Revision hersteld.</div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="error"><?php echo esc_html($error); ?></div><?php endif; ?>

        <form method="post" action="/dashboard/pages/edit?id=<?php echo (int) $page['ID']; ?>">
            <?php mol_nonce_field('pages_edit_' . (int) $page['ID']); ?>
            <label for="post_title">Titel</label>
            <input id="post_title" name="post_title" type="text" value="<?php echo esc_html((string) $page['post_title']); ?>" required>

            <label for="post_name">Slug</label>
            <input id="post_name" name="post_name" type="text" value="<?php echo esc_html((string) $page['post_name']); ?>" placeholder="bijv. over-ons">

            <p class="hint">Media selecteren: open <a href="/dashboard/media" target="_blank" rel="noopener">Media Library</a> of kies een URL uit de suggesties bij Image URL velden.</p>

            <label for="post_status">Status</label>
            <select id="post_status" name="post_status">
                <?php foreach (['draft', 'publish', 'private', 'trash'] as $statusOption): ?>
                    <option value="<?php echo esc_html($statusOption); ?>" <?php echo $statusOption === (string) ($page['post_status'] ?? 'draft') ? 'selected' : ''; ?>>
                        <?php echo esc_html(ucfirst($statusOption)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="row" style="margin-top: 24px;"><h2>Sections (ACF)</h2></div>
            <p class="hint">Geen JSON meer: je bewerkt hier gewone velden. Alleen relevante velden worden per type opgeslagen.</p>

            <div id="sections-list">
                <?php foreach ($sections as $index => $section): ?>
                    <?php
                    $type = (string) ($section['type'] ?? 'text');
                    $fields = $section['fields'] ?? [];
                    if (!is_array($fields)) {
                        $fields = [];
                    }
                    $formFields = section_fields_to_form($type, $fields);
                    ?>
                    <?php
                    $dataItems = '';
                    if (isset($formFields['items']) && is_array($formFields['items'])) {
                        $dataItems = ' data-items="' . esc_attr(json_encode($formFields['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
                    }
                    ?>
                    <div class="section-card"<?php echo $dataItems; ?>>
                        <div class="section-card-head">
                            <strong>Section <?php echo (int) $index + 1; ?></strong>
                            <button class="btn btn-danger" type="button" onclick="removeSection(this)">Verwijderen</button>
                        </div>

                        <label>Type</label>
                        <select name="section_type[<?php echo (int) $index; ?>]">
                            <?php foreach ($availableTypes as $availableType): ?>
                                <option value="<?php echo esc_html($availableType); ?>" <?php echo $availableType === $type ? 'selected' : ''; ?>><?php echo esc_html($availableType); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hint"><?php echo esc_html(section_type_hint($type)); ?></p>

                        <div class="section-grid">
                            <div class="field-wrap" data-key="headline"><label>Headline</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][headline]" value="<?php echo esc_html($formFields['headline'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="subline"><label>Subline</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][subline]" value="<?php echo esc_html($formFields['subline'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="title"><label>Titel</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][title]" value="<?php echo esc_html($formFields['title'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="image"><label>Image URL</label><input type="text" list="media-urls" name="section_fields[<?php echo (int) $index; ?>][image]" value="<?php echo esc_html($formFields['image'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="button_label"><label>Button Label</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][button_label]" value="<?php echo esc_html($formFields['button_label'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="button_url"><label>Button URL</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][button_url]" value="<?php echo esc_html($formFields['button_url'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="quote"><label>Quote</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][quote]" value="<?php echo esc_html($formFields['quote'] ?? ''); ?>"></div>
                            <div class="field-wrap" data-key="author"><label>Author</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][author]" value="<?php echo esc_html($formFields['author'] ?? ''); ?>"></div>
                            <div class="field-wrap full" data-key="role"><label>Role</label><input type="text" name="section_fields[<?php echo (int) $index; ?>][role]" value="<?php echo esc_html($formFields['role'] ?? ''); ?>"></div>
                            <div class="field-wrap full" data-key="content"><label>Content / Intro</label><textarea class="wysiwyg" name="section_fields[<?php echo (int) $index; ?>][content]"><?php echo esc_html($formFields['content'] ?? ''); ?></textarea></div>
                            <div class="field-wrap full" data-key="items_lines"><label>Items</label><textarea name="section_fields[<?php echo (int) $index; ?>][items_lines]" placeholder="features: 1 item per regel&#10;faq/stats: links|rechts per regel"><?php echo esc_html($formFields['items_lines'] ?? ''); ?></textarea></div>
                            <div class="field-wrap full portfolio-items" data-key="portfolio_items">
                                <label>Portfolio items</label>
                                <div class="portfolio-rows" data-index="<?php echo (int) $index; ?>"></div>
                                <button type="button" class="btn btn-ghost" onclick="addPortfolioRow(this)">+ rij</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row">
                <button class="btn btn-link" type="button" onclick="addSection()">+ Section toevoegen</button>
            </div>

            <div class="row">
                <small>Pagina ID: <?php echo (int) $page['ID']; ?></small>
                <div class="row" style="margin:0;">
                    <button class="btn btn-link" type="submit" name="save_action" value="draft">Opslaan Als Concept</button>
                    <button class="btn btn-link" type="submit" name="save_action" value="private">Opslaan Als Private</button>
                    <button class="btn" type="submit" name="save_action" value="publish">Publiceren</button>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Revisions</h2>
        <?php if (empty($revisions)): ?>
            <p class="hint">Nog geen revisions voor deze pagina.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr><th>ID</th><th>Actie</th><th>Status</th><th>Datum</th><th>Acties</th></tr>
                </thead>
                <tbody>
                <?php foreach ($revisions as $revision): ?>
                    <tr>
                        <td><?php echo (int) $revision['id']; ?></td>
                        <td><?php echo esc_html((string) $revision['action']); ?></td>
                        <td><?php echo esc_html((string) $revision['post_status']); ?></td>
                        <td><?php echo esc_html((string) $revision['created_at']); ?></td>
                        <td>
                            <a class="btn btn-link" href="/dashboard/pages/edit?id=<?php echo (int) $page['ID']; ?>&compare_revision=<?php echo (int) $revision['id']; ?>">Vergelijk</a>
                            <a class="btn btn-link" href="/dashboard/pages/edit?id=<?php echo (int) $page['ID']; ?>&restore_revision=<?php echo (int) $revision['id']; ?>&_wpnonce=<?php echo esc_attr(mol_create_nonce('pages_restore_revision_' . (int) $page['ID'])); ?>">Herstel</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($compareRevision): ?>
            <div style="margin-top:14px; border:1px solid var(--line); border-radius:10px; padding:12px; background:#f8fafc;">
                <h3 style="margin:0 0 8px;">Vergelijk huidige versie met revision #<?php echo (int) $compareRevision['id']; ?></h3>
                <p class="hint">Titel</p>
                <div><strong>Huidig:</strong> <?php echo esc_html((string) $page['post_title']); ?></div>
                <div><strong>Revision:</strong> <?php echo esc_html((string) $compareRevision['post_title']); ?></div>
                <p class="hint" style="margin-top:12px;">Slug</p>
                <div><strong>Huidig:</strong> <?php echo esc_html((string) $page['post_name']); ?></div>
                <div><strong>Revision:</strong> <?php echo esc_html((string) $compareRevision['post_name']); ?></div>
                <p class="hint" style="margin-top:12px;">Status</p>
                <div><strong>Huidig:</strong> <?php echo esc_html((string) ($page['post_status'] ?? '')); ?></div>
                <div><strong>Revision:</strong> <?php echo esc_html((string) $compareRevision['post_status']); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<datalist id="media-urls">
    <?php foreach ($mediaPickerItems as $mediaItem): ?>
        <option value="<?php echo esc_html((string) ($mediaItem['guid'] ?? '')); ?>">
            <?php echo esc_html((string) ($mediaItem['post_title'] ?? 'Media')); ?>
        </option>
    <?php endforeach; ?>
</datalist>

        </div>
    </main>
</div>

<template id="section-template">
    <div class="section-card">
        <div class="section-card-head">
            <strong>Nieuwe section</strong>
            <button class="btn btn-danger" type="button" onclick="removeSection(this)">Verwijderen</button>
        </div>
        <label>Type</label>
        <select data-name="section_type">
            <option value="hero">hero</option>
            <option value="text">text</option>
            <option value="cta">cta</option>
            <option value="features">features</option>
            <option value="faq">faq</option>
            <option value="media-text">media-text</option>
            <option value="services">services</option>
            <option value="portfolio">portfolio</option>
            <option value="case-study">case-study</option>
            <option value="stats">stats</option>
            <option value="testimonial">testimonial</option>
        </select>
        <p class="hint">Gebruikt: items (image|title|subject|tags per regel)</p>
        <div class="section-grid">
            <div class="field-wrap hidden" data-key="headline"><label>Headline</label><input type="text" data-name="headline"></div>
            <div class="field-wrap hidden" data-key="subline"><label>Subline</label><input type="text" data-name="subline"></div>
            <div class="field-wrap hidden" data-key="title"><label>Titel</label><input type="text" data-name="title"></div>
            <div class="field-wrap hidden" data-key="image"><label>Image URL</label><input type="text" list="media-urls" data-name="image"></div>
            <div class="field-wrap hidden" data-key="button_label"><label>Button Label</label><input type="text" data-name="button_label"></div>
            <div class="field-wrap hidden" data-key="button_url"><label>Button URL</label><input type="text" data-name="button_url"></div>
            <div class="field-wrap hidden" data-key="quote"><label>Quote</label><input type="text" data-name="quote"></div>
            <div class="field-wrap hidden" data-key="author"><label>Author</label><input type="text" data-name="author"></div>
            <div class="field-wrap full hidden" data-key="role"><label>Role</label><input type="text" data-name="role"></div>
            <div class="field-wrap full hidden" data-key="content"><label>Content / Intro</label><textarea class="wysiwyg" data-name="content"></textarea></div>
            <div class="field-wrap full hidden" data-key="items_lines"><label>Items</label><textarea data-name="items_lines" placeholder="features: 1 item per regel&#10;faq/stats: links|rechts per regel"></textarea></div>
            <div class="field-wrap full hidden portfolio-items" data-key="portfolio_items">
                <label>Portfolio items</label>
                <div class="portfolio-rows" data-index=""></div>
                <button type="button" class="btn btn-ghost" onclick="addPortfolioRow(this)">+ rij</button>
            </div>
        </div>
    </div>
</template>

<script>
var fieldsByType = <?php echo json_encode($sectionEditorFieldsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var sectionHints = <?php echo json_encode($sectionHints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var sectionSchemaMap = <?php echo json_encode(get_section_schemas(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>; // full schema for each type

function refreshSectionFields(card) {
    var select = card.querySelector('select[name^="section_type"], select[data-name="section_type"]');
    if (!select) return;

    var type = select.value;
    var allowed = fieldsByType[type] || ['title', 'content'];

    card.querySelectorAll('.field-wrap[data-key]').forEach(function (wrap) {
        var key = wrap.getAttribute('data-key');
        var visible = allowed.indexOf(key) !== -1;
        wrap.classList.toggle('hidden', !visible);

        var input = wrap.querySelector('input, textarea, select');
        if (input && !visible) {
            input.value = '';
        }
    });

    var hint = card.querySelector('.hint');
    if (hint) {
        hint.textContent = sectionHints[type] || 'Vul de velden in die je nodig hebt voor dit type.';
    }

    // portfolio custom editor
    var schema = sectionSchemaMap[type] || {};
    var hasNestedItems = schema.fields && schema.fields.items && schema.fields.items.item;
    var portfolioWrap = card.querySelector('.portfolio-items');
    if (portfolioWrap) {
        if (hasNestedItems) {
            portfolioWrap.classList.remove('hidden');
            var idx = getSectionIndex(card);
            var existing = [];
            try {
                existing = JSON.parse(card.getAttribute('data-items') || '[]');
            } catch (e) { existing = []; }
            renderPortfolioRows(portfolioWrap, existing, idx);
        } else {
            portfolioWrap.classList.add('hidden');
        }
    }

// helper to pull numeric index from one of the input names
function getSectionIndex(card) {
    var inp = card.querySelector('input[name^="section_fields"]');
    if (!inp) return null;
    var m = inp.name.match(/^section_fields\[(\d+)\]/);
    return m ? parseInt(m[1], 10) : null;
}
}

function renderPortfolioRows(wrapper, items, sectionIndex) {
    var container = wrapper.querySelector('.portfolio-rows');
    if (!container) return;
    container.innerHTML = '';
    items = Array.isArray(items) ? items : [];
    items.forEach(function(item, i) {
        addPortfolioRow(container, sectionIndex, item);
    });
}

function addPortfolioRow(buttonOrContainer, sectionIndex, itemData) {
    var container, sectionIdx;
    if (buttonOrContainer.classList && buttonOrContainer.classList.contains('portfolio-rows')) {
        container = buttonOrContainer;
        sectionIdx = sectionIndex;
    } else {
        // called from + rij button
        var row = buttonOrContainer.closest('.portfolio-items');
        if (!row) return;
        container = row.querySelector('.portfolio-rows');
        sectionIdx = parseInt(container.getAttribute('data-index'), 10);
    }
    if (!container) return;

    var itemIdx = container.children.length;
    var div = document.createElement('div');
    div.className = 'portfolio-row';
    div.innerHTML = '
        <input type="text" placeholder="Image URL" name="section_fields['+sectionIdx+'][items]['+itemIdx+'][image]" value="'+(itemData && itemData.image?itemData.image:'')+'">'
        +'<input type="text" placeholder="Title" name="section_fields['+sectionIdx+'][items]['+itemIdx+'][title]" value="'+(itemData && itemData.title?itemData.title:'')+'">'
        +'<input type="text" placeholder="Subject" name="section_fields['+sectionIdx+'][items]['+itemIdx+'][subject]" value="'+(itemData && itemData.subject?itemData.subject:'')+'">'
        +'<input type="text" placeholder="Tags (comma)" name="section_fields['+sectionIdx+'][items]['+itemIdx+'][tags]" value="'+(itemData && Array.isArray(itemData.tags)?itemData.tags.join(','):'')+'">'
        +'<button type="button" onclick="this.parentNode.remove()" class="btn btn-danger">×</button>';
    container.appendChild(div);
}


function addSection() {
    var list = document.getElementById('sections-list');
    var template = document.getElementById('section-template');
    var clone = template.content.cloneNode(true);
    var nextIndex = parseInt(list.getAttribute('data-next-index') || '<?php echo (int) $nextSectionIndex; ?>', 10);

    clone.querySelectorAll('[data-name]').forEach(function (el) {
        var key = el.getAttribute('data-name');
        if (key === 'section_type') {
            el.setAttribute('name', 'section_type[' + nextIndex + ']');
        } else {
            el.setAttribute('name', 'section_fields[' + nextIndex + '][' + key + ']');
        }
    });
    // ensure portfolio rows container knows its section index
    clone.querySelectorAll('.portfolio-rows').forEach(function(el){
        el.setAttribute('data-index', nextIndex);
    });
    // remove any data-items that might have been cloned
    clone.querySelectorAll('.section-card').forEach(function(el){ el.removeAttribute('data-items'); });

    list.appendChild(clone);
    // ensure wysiwyg editors are initialised for any textarea inside the new fragment
    initWYSIWYG(clone);
    list.setAttribute('data-next-index', String(nextIndex + 1));

    var lastCard = list.querySelector('.section-card:last-child');
    if (lastCard) {
        refreshSectionFields(lastCard);
        var select = lastCard.querySelector('select[name^="section_type"]');
        if (select) {
            select.addEventListener('change', function () { refreshSectionFields(lastCard); });
        }
    }
}

function removeSection(button) {
    var card = button.closest('.section-card');
    if (!card) return;
    card.remove();
}

document.getElementById('sections-list').setAttribute('data-next-index', '<?php echo (int) $nextSectionIndex; ?>');
document.querySelectorAll('#sections-list .section-card').forEach(function (card) {
    refreshSectionFields(card);
    var select = card.querySelector('select[name^="section_type"]');
    if (select) {
        select.addEventListener('change', function () { refreshSectionFields(card); });
    }
});

// initialize/refresh wysiwyg editor instances
function initWYSIWYG(root) {
    root = root || document;
    if (typeof tinymce === 'undefined') {
        return;
    }
    root.querySelectorAll('textarea.wysiwyg').forEach(function(el){
        if (!el.classList.contains('tox-initialized')) {
            tinymce.init({
                target: el,
                menubar: false,
                branding: false,
                license_key: 'gpl',
                plugins: 'link image lists code',
                toolbar: 'undo redo | bold italic | bullist numlist | link image | code',
                height: 200,
                setup: function(editor) {
                    editor.on('Change', function() { editor.save(); });
                }
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function(){
    initWYSIWYG();
});
</script>
</body>
</html>
