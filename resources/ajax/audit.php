<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../php/init.php';

$auth = new Auth($link);
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (!mol_current_user_can('view_audit_log')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));

$params = [];
$where = ['1=1'];

if (!empty($_GET['actor']) && ctype_digit((string) $_GET['actor'])) {
    $where[] = 'al.actor_id = :actor_id';
    $params['actor_id'] = (int) $_GET['actor'];
}

if (!empty($_GET['target']) && ctype_digit((string) $_GET['target'])) {
    $where[] = 'al.target_id = :target_id';
    $params['target_id'] = (int) $_GET['target'];
}

if (!empty($_GET['action'])) {
    $where[] = 'al.action LIKE :action';
    $params['action'] = '%' . trim((string) $_GET['action']) . '%';
}

if (!empty($_GET['since'])) {
    $since = strtotime((string) $_GET['since']);
    if ($since !== false) {
        $where[] = 'al.created_at >= :since';
        $params['since'] = date('Y-m-d H:i:s', $since);
    }
}

if (!empty($_GET['until'])) {
    $until = strtotime((string) $_GET['until']);
    if ($until !== false) {
        $where[] = 'al.created_at <= :until';
        $params['until'] = date('Y-m-d H:i:s', $until);
    }
}

$whereClause = implode(' AND ', $where);

$total = (int) get_var(
    'SELECT COUNT(*) FROM ' . table('audit_log') . ' al WHERE ' . $whereClause,
    $params
);

$offset = ($page - 1) * $perPage;

$rows = get_results(
    'SELECT al.*, a.user_login AS actor_login, a.display_name AS actor_display, t.user_login AS target_login, t.display_name AS target_display
        FROM ' . table('audit_log') . ' al
        LEFT JOIN ' . table('users') . ' a ON a.id = al.actor_id
        LEFT JOIN ' . table('users') . ' t ON t.id = al.target_id
        WHERE ' . $whereClause . '
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset',
    array_merge($params, ['limit' => $perPage, 'offset' => $offset])
);

foreach ($rows as &$row) {
    $row['meta'] = json_decode((string) ($row['meta'] ?? ''), true) ?: [];
}
unset($row);

if (isset($_GET['format']) && strtolower((string) $_GET['format']) === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-log.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['created_at', 'action', 'actor_id', 'actor_login', 'actor_display', 'target_id', 'target_login', 'target_display', 'meta']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['created_at'] ?? '',
            $row['action'] ?? '',
            $row['actor_id'] ?? '',
            $row['actor_login'] ?? '',
            $row['actor_display'] ?? '',
            $row['target_id'] ?? '',
            $row['target_login'] ?? '',
            $row['target_display'] ?? '',
            json_encode($row['meta'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
    }
    fclose($output);
    exit;
}

echo json_encode([
    'items' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
]);
