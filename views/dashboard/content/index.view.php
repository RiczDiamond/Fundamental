<?php
$selectedTypeKey = (string)($contentSelectedTypeKey ?? 'services');
$selectedTypeDefinition = is_array($contentSelectedTypeDefinition ?? null) ? $contentSelectedTypeDefinition : [];
$selectedTypeSlug = (string)($selectedTypeDefinition['slug'] ?? $selectedTypeKey);

$contentIndexTitle = trim((string)($contentTypeIndexTitle ?? ''));
if ($contentIndexTitle === '') {
    $contentIndexTitle = (string)($selectedTypeDefinition['label'] ?? 'Content');
}

$contentIndexIntro = trim((string)($contentTypeIndexIntro ?? ''));
if ($contentIndexIntro === '') {
    $contentIndexIntro = (string)($selectedTypeDefinition['description'] ?? '');
}

$e = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$baseDashboardPath = '/dashboard/' . $selectedTypeSlug;
$publicTypePath = '/' . $selectedTypeSlug;

$searchQuery = trim((string)($_GET['content_q'] ?? ''));
$statusFilter = trim((string)($_GET['content_status'] ?? ''));
$perPage = max(5, min((int)($_GET['content_per_page'] ?? 10), 50));

$statusLabels = [
    'draft' => 'Draft',
    'review' => 'In Review',
    'approved' => 'Approved',
    'published' => 'Published',
    'archived' => 'Archived',
];

if (class_exists('Symfony\\Component\\Translation\\Translator') && class_exists('Symfony\\Component\\Translation\\Loader\\ArrayLoader')) {
    $locale = strtolower(substr((string)(getenv('APP_LOCALE') ?: 'en'), 0, 2));
    if ($locale !== 'nl') {
        $locale = 'en';
    }

    $translator = new \Symfony\Component\Translation\Translator($locale);
    $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
    $translator->addResource('array', [
        'status.draft' => 'Draft',
        'status.review' => 'In Review',
        'status.approved' => 'Approved',
        'status.published' => 'Published',
        'status.archived' => 'Archived',
    ], 'en', 'dashboard');
    $translator->addResource('array', [
        'status.draft' => 'Concept',
        'status.review' => 'In review',
        'status.approved' => 'Goedgekeurd',
        'status.published' => 'Gepubliceerd',
        'status.archived' => 'Gearchiveerd',
    ], 'nl', 'dashboard');

    $statusLabels = [
        'draft' => $translator->trans('status.draft', [], 'dashboard'),
        'review' => $translator->trans('status.review', [], 'dashboard'),
        'approved' => $translator->trans('status.approved', [], 'dashboard'),
        'published' => $translator->trans('status.published', [], 'dashboard'),
        'archived' => $translator->trans('status.archived', [], 'dashboard'),
    ];
}

$formatDate = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d-m-Y H:i', $timestamp) : $value;
};

$currentPage = max(1, (int)($contentManagedPage ?? 1));
$totalPages = max(1, (int)($contentManagedPagesTotal ?? 1));

$buildPageUrl = static function (int $page) use ($baseDashboardPath, $searchQuery, $statusFilter, $perPage): string {
    $query = [
        'content_page' => $page,
        'content_per_page' => $perPage,
    ];

    if ($searchQuery !== '') {
        $query['content_q'] = $searchQuery;
    }
    if ($statusFilter !== '') {
        $query['content_status'] = $statusFilter;
    }

    return $baseDashboardPath . '?' . http_build_query($query);
};
?>
<style>
    .content-shell { display: grid; gap: 12px; }
    .content-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
    .content-head h3 { margin: 0; }
    .content-head p { margin: 4px 0 0; }
    .content-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid #dcdcde;
        border-radius: 999px;
        background: #f6f7f7;
        font-size: 12px;
        color: #3c434a;
        white-space: nowrap;
    }
    .content-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 10px; }
    .content-table .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .content-filter { display: grid; gap: 8px; grid-template-columns: minmax(0, 1fr) 180px 130px auto auto; margin-bottom: 10px; }
    .content-status { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 600; }
    .content-status.is-draft { background: #f0f0f1; color: #3c434a; }
    .content-status.is-review { background: #eef4ff; color: #1f4f91; }
    .content-status.is-approved { background: #ecfdf3; color: #0b6b3f; }
    .content-status.is-published { background: #edfaef; color: #0f6e29; }
    .content-status.is-archived { background: #fef7e0; color: #8a6700; }
    .content-meta { color: #646970; font-size: 12px; }
    .content-pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 10px; }
    .content-pagination .links { display: inline-flex; gap: 8px; }
    @media (max-width: 980px) {
        .content-toolbar { flex-direction: column; align-items: stretch; }
        .content-filter { grid-template-columns: 1fr; }
        .content-head { flex-direction: column; align-items: flex-start; }
        .content-pagination { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="content-shell">
    <div class="card">
        <?php
        $selectedTypeLabel = (string)($selectedTypeDefinition['label'] ?? $selectedTypeKey);
        require __DIR__ . '/components/list-header.view.php';
        ?>

        <?php require __DIR__ . '/components/list-toolbar.view.php'; ?>
        <?php require __DIR__ . '/components/list-table.view.php'; ?>
        <?php require __DIR__ . '/components/list-pagination.view.php'; ?>
    </div>
</div>
