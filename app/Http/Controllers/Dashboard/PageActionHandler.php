<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Services\ActionLoggerService;
use App\Services\ValidationService;

final class PageActionHandler
{
    /** @var array<string, bool> */
    private const SUPPORTED_ACTIONS = [
        'page_create' => true,
        'page_update' => true,
        'page_delete' => true,
    ];

    public function __construct(
        private readonly \Page $pageModel,
        private readonly bool $canPagesWrite,
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

        if ($action === 'page_create') {
            return $this->handleCreate($errors);
        }

        if ($action === 'page_update') {
            return $this->handleUpdate($errors);
        }

        if ($action === 'page_delete') {
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
        if (!$this->canPagesWrite) {
            $errors[] = 'Geen rechten om pagina\'s te beheren.';
            return $errors;
        }

        $pageData = $this->buildPageDataFromPost();
        $pageErrors = $this->validationService
            ? $this->validationService->validateRequired($pageData, ['title' => 'Title'])
            : [];

        if (!empty($pageErrors)) {
            return array_merge($errors, $pageErrors);
        }

        $created = $this->pageModel->create($pageData, $this->currentUserId);
        if (!$created) {
            $errors[] = $this->pageModel->getLastError() ?: 'Pagina kon niet worden aangemaakt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Page created', ['user_id' => $this->currentUserId, 'page_id' => $created]);
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        header('Location: /dashboard/pages?ok=page_created');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleUpdate(array $errors): array
    {
        if (!$this->canPagesWrite) {
            $errors[] = 'Geen rechten om pagina\'s te beheren.';
            return $errors;
        }

        $pageId = (int)($_POST['id'] ?? 0);
        if ($pageId <= 0) {
            $errors[] = 'Ongeldige pagina.';
            return $errors;
        }

        $pageData = $this->buildPageDataFromPost();
        $pageErrors = $this->validationService
            ? $this->validationService->validateRequired($pageData, ['title' => 'Title'])
            : [];

        if (!empty($pageErrors)) {
            return array_merge($errors, $pageErrors);
        }

        if (!$this->pageModel->update($pageId, $pageData, $this->currentUserId)) {
            $errors[] = $this->pageModel->getLastError() ?: 'Pagina kon niet worden bijgewerkt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Page updated', ['user_id' => $this->currentUserId, 'page_id' => $pageId]);
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        header('Location: /dashboard/pages?ok=page_updated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleDelete(array $errors): array
    {
        if (!$this->canPagesWrite) {
            $errors[] = 'Geen rechten om pagina\'s te beheren.';
            return $errors;
        }

        $pageId = (int)($_POST['id'] ?? 0);
        if ($pageId <= 0) {
            $errors[] = 'Ongeldige pagina.';
            return $errors;
        }

        if (!$this->pageModel->delete($pageId)) {
            $errors[] = $this->pageModel->getLastError() ?: 'Pagina kon niet worden verwijderd.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        header('Location: /dashboard/pages?ok=page_deleted');
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function buildPageDataFromPost(): array
    {
        $heroPayload = [
            'hero' => [
                'title' => ($this->sanitizeText)($_POST['hero_title'] ?? ''),
                'subtitle' => ($this->sanitizeText)($_POST['hero_subtitle'] ?? ''),
                'cta_label' => ($this->sanitizeText)($_POST['hero_cta_label'] ?? ''),
                'cta_url' => ($this->sanitizeUrl)($_POST['hero_cta_url'] ?? ''),
            ],
        ];

        return [
            'title' => ($this->sanitizeText)($_POST['title'] ?? ''),
            'slug' => ($this->sanitizeText)($_POST['slug'] ?? ''),
            'template' => ($this->sanitizeText)($_POST['template'] ?? 'default'),
            'page_type' => ($this->sanitizeText)($_POST['page_type'] ?? 'basic_page'),
            'template_payload_json' => (string)json_encode($heroPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'excerpt' => ($this->sanitizeHtml)($_POST['excerpt'] ?? ''),
            'content' => ($this->sanitizeHtml)($_POST['content'] ?? ''),
            'meta_title' => ($this->sanitizeText)($_POST['meta_title'] ?? ''),
            'meta_description' => ($this->sanitizeText)($_POST['meta_description'] ?? ''),
            'status' => ($this->sanitizeText)($_POST['status'] ?? 'draft'),
            'published_at' => ($this->sanitizeText)($_POST['published_at'] ?? ''),
        ];
    }
}
