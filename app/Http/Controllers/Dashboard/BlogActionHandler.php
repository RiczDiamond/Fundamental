<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Services\ActionLoggerService;
use App\Services\ValidationService;

final class BlogActionHandler
{
    /** @var array<string, bool> */
    private const SUPPORTED_ACTIONS = [
        'blog_create' => true,
        'blog_update' => true,
        'blog_duplicate' => true,
        'blog_bulk' => true,
        'blog_inline_status' => true,
        'blog_restore_revision' => true,
        'blog_autosave' => true,
        'blog_preview_token' => true,
        'blog_delete' => true,
    ];

    public function __construct(
        private readonly \Blog $blogModel,
        private readonly bool $canBlogWrite,
        private readonly int $currentUserId,
        private readonly ?ValidationService $validationService,
        private readonly ?ActionLoggerService $actionLogger,
        private readonly \Closure $sanitizeText,
        private readonly \Closure $sanitizeHtml,
        private readonly \Closure $sanitizeUrl,
        private readonly \Closure $deleteCacheKeys,
    ) {
    }

    public function supports(string $action): bool
    {
        return isset(self::SUPPORTED_ACTIONS[$action]);
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    public function handle(string $action, array $errors): array
    {
        if (!$this->supports($action)) {
            return $errors;
        }

        if ($action === 'blog_create') {
            return $this->handleCreate($errors);
        }

        if ($action === 'blog_update') {
            return $this->handleUpdate($errors);
        }

        if ($action === 'blog_duplicate') {
            return $this->handleDuplicate($errors);
        }

        if ($action === 'blog_bulk') {
            return $this->handleBulk($errors);
        }

        if ($action === 'blog_inline_status') {
            return $this->handleInlineStatus($errors);
        }

        if ($action === 'blog_restore_revision') {
            return $this->handleRestoreRevision($errors);
        }

        if ($action === 'blog_autosave') {
            return $this->handleAutosave($errors);
        }

        if ($action === 'blog_preview_token') {
            return $this->handlePreviewToken($errors);
        }

        if ($action === 'blog_delete') {
            return $this->handleDelete($errors);
        }

        return $errors;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleCreate(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $blogData = $this->buildBlogDataFromPost();
        $blogErrors = $this->validationService
            ? $this->validationService->validateRequired($blogData, ['title' => 'Title'])
            : [];

        if (!empty($blogErrors)) {
            return array_merge($errors, $blogErrors);
        }

        $created = $this->blogModel->create($blogData, $this->currentUserId);
        if (!$created) {
            $errors[] = $this->blogModel->getLastError() ?: 'Blogpost kon niet worden aangemaakt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Blog created', ['user_id' => $this->currentUserId, 'blog_id' => $created]);
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_created');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleUpdate(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $blogId = (int)($_POST['id'] ?? 0);
        if ($blogId <= 0) {
            $errors[] = 'Ongeldige blogpost.';
            return $errors;
        }

        $blogData = $this->buildBlogDataFromPost();
        $blogErrors = $this->validationService
            ? $this->validationService->validateRequired($blogData, ['title' => 'Title'])
            : [];

        if (!empty($blogErrors)) {
            return array_merge($errors, $blogErrors);
        }

        if (!$this->blogModel->update($blogId, $blogData, $this->currentUserId)) {
            $errors[] = $this->blogModel->getLastError() ?: 'Blogpost kon niet worden bijgewerkt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Blog updated', ['user_id' => $this->currentUserId, 'blog_id' => $blogId]);
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_updated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleDuplicate(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $blogId = (int)($_POST['id'] ?? 0);
        if ($blogId <= 0) {
            $errors[] = 'Ongeldige blogpost.';
            return $errors;
        }

        if (!$this->blogModel->duplicate($blogId, $this->currentUserId)) {
            $errors[] = 'Concept kon niet worden gedupliceerd.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_duplicated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleBulk(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $bulkAction = trim((string)($_POST['bulk_action'] ?? ''));
        $selected = $_POST['selected_ids'] ?? [];
        $selectedIds = is_array($selected) ? $selected : [];

        if ($bulkAction === '' || empty($selectedIds)) {
            $errors[] = 'Selecteer items en een bulk-actie.';
            return $errors;
        }

        $affected = 0;
        if ($bulkAction === 'delete') {
            $affected = $this->blogModel->bulkDelete($selectedIds);
        } elseif (in_array($bulkAction, ['draft', 'published', 'scheduled', 'archived'], true)) {
            $affected = $this->blogModel->bulkUpdateStatus($selectedIds, $bulkAction, $this->currentUserId);
        }

        if ($affected <= 0) {
            $errors[] = 'Bulk-actie kon niet worden uitgevoerd.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_bulk_updated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleInlineStatus(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $blogId = (int)($_POST['id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'draft'));

        if ($blogId <= 0) {
            $errors[] = 'Ongeldige blogpost.';
            return $errors;
        }

        if ($this->blogModel->bulkUpdateStatus([$blogId], $status, $this->currentUserId) <= 0) {
            $errors[] = 'Status kon niet inline worden bijgewerkt.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_updated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleRestoreRevision(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $revisionId = (int)($_POST['revision_id'] ?? 0);
        $blogId = (int)($_POST['blog_id'] ?? 0);

        if ($revisionId <= 0 || $blogId <= 0) {
            $errors[] = 'Ongeldige revisie.';
            return $errors;
        }

        if (!$this->blogModel->restoreRevision($revisionId, $this->currentUserId)) {
            $errors[] = $this->blogModel->getLastError() ?: 'Revisie kon niet worden teruggezet.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_revision_restored');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleAutosave(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om autosave uit te voeren.';
            return $errors;
        }

        $blogId = (int)($_POST['id'] ?? 0);
        $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if ($blogId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Autosave werkt alleen voor bestaande posts.']);
                exit;
            }
            $errors[] = 'Autosave werkt alleen voor bestaande posts.';
            return $errors;
        }

        $ok = $this->blogModel->saveAutosave($blogId, $this->currentUserId, [
            'title' => trim((string)($_POST['title'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'featured_image' => trim((string)($_POST['featured_image'] ?? '')),
            'intro' => trim((string)($_POST['intro'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? '')),
            'tags' => trim((string)($_POST['tags'] ?? '')),
            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
            'og_image' => trim((string)($_POST['og_image'] ?? '')),
            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
            'content' => trim((string)($_POST['content'] ?? '')),
            'status' => trim((string)($_POST['status'] ?? 'draft')),
            'scheduled_at' => trim((string)($_POST['scheduled_at'] ?? '')),
        ]);

        if ($ok) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'saved_at' => date('Y-m-d H:i:s')]);
                exit;
            }
            header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_autosaved');
            exit;
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => ($this->blogModel->getLastError() ?: 'Autosave kon niet worden opgeslagen.'),
            ]);
            exit;
        }

        $errors[] = $this->blogModel->getLastError() ?: 'Autosave kon niet worden opgeslagen.';
        return $errors;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handlePreviewToken(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om preview links te maken.';
            return $errors;
        }

        $blogId = (int)($_POST['blog_id'] ?? 0);
        $role = trim((string)($_POST['required_role'] ?? 'all'));
        $ttlHours = max(1, min((int)($_POST['ttl_hours'] ?? 24), 168));
        $preview = $this->blogModel->createPreviewToken($blogId, $role, $this->currentUserId, $ttlHours);

        if (!$preview) {
            $errors[] = 'Preview-link kon niet worden aangemaakt.';
            return $errors;
        }

        header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_preview_created&preview_token=' . urlencode($preview['token']));
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleDelete(array $errors): array
    {
        if (!$this->canBlogWrite) {
            $errors[] = 'Geen rechten om blogs te beheren.';
            return $errors;
        }

        $blogId = (int)($_POST['id'] ?? 0);
        if ($blogId <= 0) {
            $errors[] = 'Ongeldige blogpost.';
            return $errors;
        }

        if (!$this->blogModel->delete($blogId)) {
            $errors[] = 'Blogpost kon niet worden verwijderd.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['blog.categories.overview.20', 'sitemap.xml.public']);
        header('Location: /dashboard/blogs?ok=blog_deleted');
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function buildBlogDataFromPost(): array
    {
        return [
            'title' => ($this->sanitizeText)($_POST['title'] ?? ''),
            'slug' => ($this->sanitizeText)($_POST['slug'] ?? ''),
            'featured_image' => ($this->sanitizeUrl)($_POST['featured_image'] ?? ''),
            'intro' => ($this->sanitizeHtml)($_POST['intro'] ?? ''),
            'category' => ($this->sanitizeText)($_POST['category'] ?? ''),
            'tags' => ($this->sanitizeText)($_POST['tags'] ?? ''),
            'meta_title' => ($this->sanitizeText)($_POST['meta_title'] ?? ''),
            'meta_description' => ($this->sanitizeText)($_POST['meta_description'] ?? ''),
            'og_image' => ($this->sanitizeUrl)($_POST['og_image'] ?? ''),
            'excerpt' => ($this->sanitizeHtml)($_POST['excerpt'] ?? ''),
            'content' => ($this->sanitizeHtml)($_POST['content'] ?? ''),
            'status' => ($this->sanitizeText)($_POST['status'] ?? 'draft'),
            'scheduled_at' => ($this->sanitizeText)($_POST['scheduled_at'] ?? ''),
        ];
    }
}
