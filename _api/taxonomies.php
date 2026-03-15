<?php

/**
 * Simple taxonomy + term API.
 *
 * Endpoints:
 * - GET /api/taxonomies
 * - GET /api/terms?taxonomy={taxonomy}
 * - GET /api/terms/{id}
 * - POST /api/terms
 */

function handle_api_taxonomies(?string $id): void
{
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_api_taxonomies_get($id);
            break;
        case 'POST':
            handle_api_terms_post();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handle_api_taxonomies_get(?string $id): void
{
    if ($id) {
        $term = get_row('SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description FROM ' . table('terms') . ' t JOIN ' . table('term_taxonomy') . ' tt ON tt.term_id = t.term_id WHERE t.term_id = :id LIMIT 1', ['id'=> $id]);
        if (!$term) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }
        echo json_encode($term);
        return;
    }

    $taxonomy = trim((string) ($_GET['taxonomy'] ?? ''));
    if ($taxonomy === '') {
        // Return list of known taxonomies (hardcoded for now)
        echo json_encode(['category', 'tag']);
        return;
    }

    $terms = mol_get_terms($taxonomy);
    echo json_encode($terms);
}

function handle_api_terms_post(): void
{
    $input = mol_get_json_body();
    $taxonomy = trim((string) ($input['taxonomy'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $slug = trim((string) ($input['slug'] ?? ''));

    if ($taxonomy === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'taxonomy and name are required']);
        return;
    }

    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $name));
    }

    $term = mol_get_or_create_term($name, $slug, $taxonomy);
    if (!$term) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create term']);
        return;
    }

    echo json_encode($term);
}
