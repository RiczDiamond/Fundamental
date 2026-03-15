<?php

/**
 * Simple REST endpoints for posts (from the `posts` table).
 *
 * To test:
 *  - GET  /api/posts
 *  - GET  /api/posts/{id}
 *  - POST /api/posts          (JSON body)
 *  - PUT  /api/posts/{id}     (JSON body)
 *  - DELETE /api/posts/{id}
 */

function handle_api_posts(?string $id): void
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

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_api_posts_get($id);
            break;
        case 'POST':
            handle_api_posts_post();
            break;
        case 'PUT':
        case 'PATCH':
            handle_api_posts_put($id);
            break;
        case 'DELETE':
            handle_api_posts_delete($id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_posts_get(?string $id): void
{
    if ($id) {
        $row = get_row('SELECT * FROM ' . table('posts') . ' WHERE ID = :id', ['id' => $id]);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            return;
        }

        // Attach meta and terms.
        $row['meta'] = mol_get_post_meta((int) $id);
        $row['terms'] = mol_get_post_terms((int) $id);

        echo json_encode($row);
        return;
    }

    $postType = trim((string) ($_GET['post_type'] ?? ''));
    $params = [];
    $where = '';

    if ($postType !== '') {
        if (!mol_is_post_type($postType)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown post type']);
            return;
        }

        $where = ' WHERE post_type = :post_type';
        $params['post_type'] = $postType;
    }

    $rows = get_results('SELECT * FROM ' . table('posts') . $where . ' ORDER BY post_date DESC LIMIT 100', $params);

    // Attach meta for each post so the admin can display custom-fields counts.
    foreach ($rows as &$row) {
        $row['meta'] = mol_get_post_meta((int) ($row['ID'] ?? 0));
    }
    unset($row);

    echo json_encode($rows);
}

function handle_api_posts_post(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $hasContent = isset($input['post_content']) && trim((string) ($input['post_content'] ?? '')) !== '';
    $hasBlocks = !empty($input['post_blocks']) && is_array($input['post_blocks']);

    if (empty($input['post_title']) || (!$hasContent && !$hasBlocks)) {
        http_response_code(400);
        echo json_encode(['error' => 'post_title and post_content or post_blocks are required']);
        return;
    }

    $postType = trim((string) ($input['post_type'] ?? 'post'));
    if ($postType === '' || !mol_is_post_type($postType)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post_type']);
        return;
    }

    $slug = trim((string) ($input['post_name'] ?? ''));
    if ($slug === '') {
        $slug = mol_sanitize_slug((string) $input['post_title']);
    }
    $slug = mol_unique_post_slug($slug, $postType);

    $now = date('Y-m-d H:i:s');

    $content = $hasBlocks
        ? json_encode($input['post_blocks'], JSON_THROW_ON_ERROR)
        : (string) ($input['post_content'] ?? '');

    $data = [
        'post_title'   => $input['post_title'],
        'post_content' => $content,
        'post_status'  => $input['post_status'] ?? 'draft',
        'post_date'    => $now,
        'post_type'    => $postType,
        'post_name'    => $slug,
    ];

    $ok = insert('posts', $data);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Insert failed']);
        return;
    }

    $id = (int) get_var('SELECT LAST_INSERT_ID()');

    // Save meta fields if provided.
    if (!empty($input['meta']) && is_array($input['meta'])) {
        foreach ($input['meta'] as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            mol_update_post_meta($id, $key, is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR));
        }
    }

    // Save taxonomy terms if provided.
    if (!empty($input['terms']) && is_array($input['terms'])) {
        foreach ($input['terms'] as $taxonomy => $termIds) {
            if (!is_string($taxonomy) || !is_array($termIds)) {
                continue;
            }
            // Accept numeric IDs or term slugs.
            $termIdsClean = array_values(array_filter(array_map(function ($t) {
                if (is_int($t) || ctype_digit((string) $t)) {
                    return (int) $t;
                }
                return null;
            }, $termIds)));
            if (count($termIdsClean)) {
                mol_set_post_terms($id, $taxonomy, $termIdsClean);
            }
        }
    }

    http_response_code(201);
    echo json_encode(['id' => $id]);
}

function handle_api_posts_put(?string $id): void
{
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Request body required']);
        return;
    }

    $allowed = ['post_title', 'post_content', 'post_status', 'post_type'];
    $data = array_intersect_key($input, array_flip($allowed));

    // Allow saving blocks as JSON if provided.
    if (!empty($input['post_blocks']) && is_array($input['post_blocks'])) {
        $data['post_content'] = json_encode($input['post_blocks'], JSON_THROW_ON_ERROR);
    }

    if (isset($data['post_type'])) {
        $postType = trim((string) $data['post_type']);
        if ($postType === '' || !mol_is_post_type($postType)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid post_type']);
            return;
        }
        $data['post_type'] = $postType;
    }

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided']);
        return;
    }

    if (isset($input['post_name'])) {
        $slug = trim((string) $input['post_name']);
        if ($slug === '') {
            $slug = mol_sanitize_slug((string) $input['post_title']);
        }
        $slug = mol_unique_post_slug($slug, $data['post_type'] ?? 'post', $id);
        $data['post_name'] = $slug;
    }

    $ok = update('posts', $data, ['ID' => $id]);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
        return;
    }

    // Save meta fields if provided.
    if (!empty($input['meta']) && is_array($input['meta'])) {
        foreach ($input['meta'] as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            mol_update_post_meta($id, $key, is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR));
        }
    }

    // Save taxonomy terms if provided.
    if (!empty($input['terms']) && is_array($input['terms'])) {
        foreach ($input['terms'] as $taxonomy => $termIds) {
            if (!is_string($taxonomy) || !is_array($termIds)) {
                continue;
            }
            $termIdsClean = array_values(array_filter(array_map(function ($t) {
                if (is_int($t) || ctype_digit((string) $t)) {
                    return (int) $t;
                }
                return null;
            }, $termIds)));
            if (count($termIdsClean)) {
                mol_set_post_terms($id, $taxonomy, $termIdsClean);
            }
        }
    }

    echo json_encode(['success' => true]);
}

function handle_api_posts_delete(?string $id): void
{
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        return;
    }

    $ok = delete('posts', ['ID' => $id]);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Delete failed']);
        return;
    }

    echo json_encode(['success' => true]);
}
