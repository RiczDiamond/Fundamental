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
        echo json_encode($row);
        return;
    }

    $rows = get_results('SELECT * FROM ' . table('posts') . ' ORDER BY post_date DESC LIMIT 100');
    echo json_encode($rows);
}

function handle_api_posts_post(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($input['post_title']) || empty($input['post_content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'post_title and post_content are required']);
        return;
    }

    $now = date('Y-m-d H:i:s');
    $data = [
        'post_title'   => $input['post_title'],
        'post_content' => $input['post_content'],
        'post_status'  => $input['post_status'] ?? 'draft',
        'post_date'    => $now,
        'post_type'    => $input['post_type'] ?? 'post',
    ];

    $ok = insert('posts', $data);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Insert failed']);
        return;
    }

    $id = get_var('SELECT LAST_INSERT_ID()');
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

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided']);
        return;
    }

    $ok = update('posts', $data, ['ID' => $id]);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
        return;
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
