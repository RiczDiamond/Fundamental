<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Services\ActionLoggerService;
use App\Services\ValidationService;

final class ContentActionHandler
{
    /** @var array<string, bool> */
    private const SUPPORTED_ACTIONS = [
        'content_create' => true,
        'content_update' => true,
        'content_delete' => true,
    ];

    public function __construct(
        private readonly \ContentItem $contentItemModel,
        private readonly \ContentTypeRegistry $contentTypeRegistry,
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

        if ($action === 'content_create') {
            return $this->handleCreate($errors);
        }

        if ($action === 'content_update') {
            return $this->handleUpdate($errors);
        }

        if ($action === 'content_delete') {
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
            $errors[] = 'Geen rechten om content te beheren.';
            return $errors;
        }

        $contentType = trim((string)($_POST['content_type'] ?? ''));
        $typeDefinition = $this->contentTypeRegistry->getByKey($contentType);
        if (!$typeDefinition) {
            $errors[] = 'Onbekend content type.';
            return $errors;
        }

        $contentData = $this->buildContentDataFromPost($typeDefinition, $contentType);
        $contentErrors = $this->validationService
            ? $this->validationService->validateRequired($contentData, ['title' => 'Title'])
            : [];

        if (!empty($contentErrors)) {
            return array_merge($errors, $contentErrors);
        }

        $created = $this->contentItemModel->create($contentData, $this->currentUserId);
        if (!$created) {
            $errors[] = $this->contentItemModel->getLastError() ?: 'Content item kon niet worden aangemaakt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Content created', [
                'user_id' => $this->currentUserId,
                'content_id' => $created,
                'type' => $contentType,
            ]);
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        $redirectSlug = (string)($typeDefinition['slug'] ?? $contentType);
        header('Location: /dashboard/' . rawurlencode($redirectSlug) . '?ok=content_created');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleUpdate(array $errors): array
    {
        if (!$this->canPagesWrite) {
            $errors[] = 'Geen rechten om content te beheren.';
            return $errors;
        }

        $itemId = (int)($_POST['id'] ?? 0);
        $contentType = trim((string)($_POST['content_type'] ?? ''));
        $typeDefinition = $this->contentTypeRegistry->getByKey($contentType);

        if ($itemId <= 0) {
            $errors[] = 'Ongeldig content item.';
            return $errors;
        }

        if (!$typeDefinition) {
            $errors[] = 'Onbekend content type.';
            return $errors;
        }

        $contentData = $this->buildContentDataFromPost($typeDefinition, $contentType);
        $contentErrors = $this->validationService
            ? $this->validationService->validateRequired($contentData, ['title' => 'Title'])
            : [];

        if (!empty($contentErrors)) {
            return array_merge($errors, $contentErrors);
        }

        $updated = $this->contentItemModel->update($itemId, $contentData, $this->currentUserId);
        if (!$updated) {
            $errors[] = $this->contentItemModel->getLastError() ?: 'Content item kon niet worden bijgewerkt.';
            return $errors;
        }

        if ($this->actionLogger) {
            $this->actionLogger->info('Content updated', [
                'user_id' => $this->currentUserId,
                'content_id' => $itemId,
                'type' => $contentType,
            ]);
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        $redirectSlug = (string)($typeDefinition['slug'] ?? $contentType);
        header('Location: /dashboard/' . rawurlencode($redirectSlug) . '?ok=content_updated');
        exit;
    }

    /**
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    private function handleDelete(array $errors): array
    {
        if (!$this->canPagesWrite) {
            $errors[] = 'Geen rechten om content te beheren.';
            return $errors;
        }

        $itemId = (int)($_POST['id'] ?? 0);
        $contentType = trim((string)($_POST['content_type'] ?? ''));
        $typeDefinition = $this->contentTypeRegistry->getByKey($contentType);

        if ($itemId <= 0) {
            $errors[] = 'Ongeldig content item.';
            return $errors;
        }

        if (!$this->contentItemModel->delete($itemId)) {
            $errors[] = $this->contentItemModel->getLastError() ?: 'Content item kon niet worden verwijderd.';
            return $errors;
        }

        ($this->deleteCacheKeys)(['sitemap.xml.public']);
        $redirectSlug = (string)($typeDefinition['slug'] ?? 'services');
        header('Location: /dashboard/' . rawurlencode($redirectSlug) . '?ok=content_deleted');
        exit;
    }

    /**
     * @param array<string, mixed> $typeDefinition
     * @return array<string, mixed>
     */
    private function buildContentDataFromPost(array $typeDefinition, string $contentType): array
    {
        $payload = [];
        $fields = isset($typeDefinition['fields']) && is_array($typeDefinition['fields'])
            ? $typeDefinition['fields']
            : [];

        foreach ($fields as $field) {
            $fieldName = trim((string)($field['name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }

            $fieldValue = (string)($_POST['payload_' . $fieldName] ?? '');
            $fieldType = (string)($field['type'] ?? 'text');
            if ($fieldType === 'textarea') {
                $payload[$fieldName] = ($this->sanitizeHtml)($fieldValue);
            } elseif ($fieldType === 'media') {
                $payload[$fieldName] = ($this->sanitizeUrl)($fieldValue);
            } else {
                $payload[$fieldName] = ($this->sanitizeText)($fieldValue);
            }
        }

        return [
            'type' => $contentType,
            'title' => ($this->sanitizeText)((string)($_POST['title'] ?? '')),
            'slug' => ($this->sanitizeText)((string)($_POST['slug'] ?? '')),
            'excerpt' => ($this->sanitizeHtml)((string)($_POST['excerpt'] ?? '')),
            'content' => ($this->sanitizeHtml)((string)($_POST['content'] ?? '')),
            'featured_image' => ($this->sanitizeUrl)((string)($_POST['featured_image'] ?? '')),
            'meta_title' => ($this->sanitizeText)((string)($_POST['meta_title'] ?? '')),
            'meta_description' => ($this->sanitizeText)((string)($_POST['meta_description'] ?? '')),
            'status' => ($this->sanitizeText)((string)($_POST['status'] ?? 'draft')),
            'published_at' => ($this->sanitizeText)((string)($_POST['published_at'] ?? '')),
            'starts_at' => ($this->sanitizeText)((string)($_POST['starts_at'] ?? '')),
            'ends_at' => ($this->sanitizeText)((string)($_POST['ends_at'] ?? '')),
            'payload_json' => $payload,
        ];
    }
}
