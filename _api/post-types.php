<?php

/**
 * API endpoint for managing post types.
 *
 * Endpoints:
 * - GET /api/post-types
 * - GET /api/post-types/{name}
 * - POST /api/post-types
 * - PATCH /api/post-types/{name}
 * - DELETE /api/post-types/{name}
 */

function handle_api_post_types(?string $id): void
{
    $auth = new Auth($GLOBALS['link']);

    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }

    $current = $auth->current_user();
    if ((int) ($current['user_status'] ?? 0) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!mol_role_has_capability((string) ($current['user_role'] ?? ''), 'manage_post_types')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_api_post_types_get($id);
            break;
        case 'POST':
            handle_api_post_types_post();
            break;
        case 'PATCH':
        case 'PUT':
            handle_api_post_types_put($id);
            break;
        case 'DELETE':
            handle_api_post_types_delete($id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_post_types_get(?string $id): void
{
    $types = mol_get_post_types();
    $builtin = [];

    // Determine which ones are builtin (registered via mol_register_post_type).
    global $mol_post_types;

    foreach ($types as $name => $args) {
        $types[$name]['builtin'] = isset($mol_post_types[$name]);
    }

    if ($id) {
        if (!isset($types[$id])) {
            http_response_code(404);
            echo json_encode(['error' => 'Post type not found']);
            return;
        }
        $result = $types[$id];
        $result['name'] = $id;
        echo json_encode($result);
        return;
    }

    $out = [];
    foreach ($types as $name => $type) {
        $type['name'] = $name;
        $out[] = $type;
    }
    echo json_encode($out);
}

function handle_api_post_types_post(): void
{
    $input = mol_get_json_body();

    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post type name']);
        return;
    }

    $existing = mol_get_post_types();
    if (isset($existing[$name])) {
        http_response_code(409);
        echo json_encode(['error' => 'Post type already exists']);
        return;
    }

    $labels = $input['labels'] ?? [];
    if (!is_array($labels) || empty($labels['singular']) || empty($labels['plural'])) {
        http_response_code(400);
        echo json_encode(['error' => 'labels.singular and labels.plural are required']);
        return;
    }

    $public = isset($input['public']) ? (bool) $input['public'] : true;
    $hasArchive = isset($input['has_archive']) ? (bool) $input['has_archive'] : true;

    $supports = is_array($input['supports']) ? array_values(array_filter($input['supports'], 'is_string')) : [];
    $taxonomies = is_array($input['taxonomies']) ? array_values(array_filter($input['taxonomies'], 'is_string')) : [];

    $config = mol_get_post_types_config();
    $menuOrder = isset($input['menu_order']) ? (int) $input['menu_order'] : 0;
    $menuIcon = isset($input['menu_icon']) ? (string) $input['menu_icon'] : '';

    $config[$name] = [
        'labels' => [
            'singular' => trim((string) $labels['singular']),
            'plural' => trim((string) $labels['plural']),
        ],
        'public' => $public,
        'has_archive' => $hasArchive,
        'supports' => $supports,
        'taxonomies' => $taxonomies,
        'menu_order' => $menuOrder,
        'menu_icon' => $menuIcon,
    ];

    if (!mol_set_post_types_config($config)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save post type']);
        return;
    }

    echo json_encode(['success' => true]);
}

function handle_api_post_types_put(?string $id): void
{
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Post type name is required']);
        return;
    }

    $types = mol_get_post_types();
    if (!isset($types[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Post type not found']);
        return;
    }

    // Do not allow editing builtin post type name.
    global $mol_post_types;
    $isBuiltin = isset($mol_post_types[$id]);

    $input = mol_get_json_body();
    if (!mol_require_valid_nonce('global_csrf')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        return;
    }

    $config = mol_get_post_types_config();
    $existing = $types[$id];

    $labels = $existing['labels'] ?? [];
    if (isset($input['labels']) && is_array($input['labels'])) {
        if (isset($input['labels']['singular'])) {
            $labels['singular'] = trim((string) $input['labels']['singular']);
        }
        if (isset($input['labels']['plural'])) {
            $labels['plural'] = trim((string) $input['labels']['plural']);
        }
    }

    $public = isset($input['public']) ? (bool) $input['public'] : ($existing['public'] ?? true);
    $hasArchive = isset($input['has_archive']) ? (bool) $input['has_archive'] : ($existing['has_archive'] ?? true);

    $supports = is_array($input['supports']) ? array_values(array_filter($input['supports'], 'is_string')) : ($existing['supports'] ?? []);
    $taxonomies = is_array($input['taxonomies']) ? array_values(array_filter($input['taxonomies'], 'is_string')) : ($existing['taxonomies'] ?? []);

    $menuOrder = isset($input['menu_order']) ? (int) $input['menu_order'] : ($existing['menu_order'] ?? 0);
    $menuIcon = isset($input['menu_icon']) ? (string) $input['menu_icon'] : ($existing['menu_icon'] ?? '');

    $config[$id] = [
        'labels' => $labels,
        'public' => $public,
        'has_archive' => $hasArchive,
        'supports' => $supports,
        'taxonomies' => $taxonomies,
        'menu_order' => $menuOrder,
        'menu_icon' => $menuIcon,
    ];

    if (!mol_set_post_types_config($config)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save post type']);
        return;
    }

    echo json_encode(['success' => true]);
}

function handle_api_post_types_delete(?string $id): void
{
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Post type name is required']);
        return;
    }

    global $mol_post_types;
    if (isset($mol_post_types[$id])) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete built-in post type']);
        return;
    }

    $config = mol_get_post_types_config();
    if (!isset($config[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Post type not found']);
        return;
    }

    unset($config[$id]);
    if (!mol_set_post_types_config($config)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not delete post type']);
        return;
    }

    echo json_encode(['success' => true]);
}
