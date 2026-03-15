<?php

// Return a fresh nonce for a given action. Used by the frontend to avoid CSRF token reuse.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

// Always return a single global nonce for dashboard/API usage.
$action = 'global_csrf';
$nonce = $_SESSION['nonces'][$action] ?? null;
if (!is_string($nonce) || $nonce === '') {
    $nonce = mol_get_nonce($action);
}

echo json_encode(['nonce' => $nonce]);
