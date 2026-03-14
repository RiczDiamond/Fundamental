<?php

// Save page sections via AJAX.
// Accepts the same POST fields as /dashboard/pages/edit but returns JSON.

// allow legacy form submissions
$input = $_POST;

// JSON body support
$raw = file_get_contents('php://input');
if ($raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$pageId = absint($input['page_id'] ?? 0);
if ($pageId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ongeldige pagina ID']);
    exit;
}

$nonceAction = 'pages_edit_' . $pageId;
if (!mol_require_valid_nonce($nonceAction, '_wpnonce', $input)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.']);
    exit;
}

$title = sanitize_text_field($input['post_title'] ?? '');
$slugInput = sanitize_text_field($input['post_name'] ?? '');
$status = sanitize_text_field($input['post_status'] ?? 'draft');
$saveAction = sanitize_text_field($input['save_action'] ?? 'save');

$submittedTypes = $input['section_type'] ?? [];
$submittedFields = $input['section_fields'] ?? [];

$normalizedSections = [];

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Titel is verplicht.']);
    exit;
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
        'attrs' => [],
    ];
}

// ensure unique slug
$slug = sanitize_title($slugInput !== '' ? $slugInput : $title);
if ($slug === '') {
    $slug = 'pagina-' . $pageId;
}

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

$sectionsJson = json_encode($normalizedSections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($sectionsJson)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Kon sections niet opslaan.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
create_post_revision($link, $page = ['ID' => $pageId, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status], $userId, 'update');

$updateStmt = $link->prepare("\n                UPDATE posts\n                SET post_title = :title,\n                    post_name = :slug,\n                    post_status = :status,\n                    post_modified = NOW()\n                WHERE ID = :id\n                  AND post_type = 'page'\n                LIMIT 1\n            ");
$updateStmt->execute([
    'title' => $title,
    'slug' => $slug,
    'status' => $status,
    'id' => $pageId,
]);

upsert_post_meta($link, $pageId, '_sections_json', $sectionsJson);

echo json_encode([
    'success' => true,
    'message' => 'Opgeslagen.',
    'slug' => $slug,
    'sections_json' => $sectionsJson,
]);
