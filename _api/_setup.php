<?php

// API entrypoint used for requests like: /api/posts
// This file is included from index.php, which already loads resources/php/init.php.

// Always respond with JSON
header('Content-Type: application/json; charset=utf-8');
// Adjust CORS as needed for your frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$raw = trim($_GET['url'] ?? '', "/ \t\r\n");
$segments = array_values(array_filter(explode('/', $raw)));

// expected path: /api/{resource}/{id?}
if (count($segments) === 0 || $segments[0] !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$resource = $segments[1] ?? null;
$id = $segments[2] ?? null;

switch ($resource) {
    case 'posts':
        require_once __DIR__ . '/posts.php';
        handle_api_posts($id);
        break;

    case 'auth':
        require_once __DIR__ . '/auth.php';
        handle_api_auth($id);
        break;

    case 'account':
        require_once __DIR__ . '/account.php';
        handle_api_account($id);
        break;

    case 'users':
        require_once __DIR__ . '/users.php';
        handle_api_users($id);
        break;


    case 'taxonomies':
        require_once __DIR__ . '/taxonomies.php';
        handle_api_taxonomies($id);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found']);
        break;
}
