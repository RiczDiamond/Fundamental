<?php

class ContentItem
{
    private $link;
    private $table = 'content_items';
    private $tableExistsCache = [];
    private $columnExistsCache = [];
    private $lastError = '';
    private $typeRegistry;

    public function __construct($link)
    {
        $this->link = $link;
        $this->typeRegistry = new ContentTypeRegistry();
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function clearLastError()
    {
        $this->lastError = '';
    }

    private function setLastError($message)
    {
        $this->lastError = trim((string)$message);
        return false;
    }

    private function tableExists($table)
    {
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $stmt = $this->link->prepare(
                'SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table]);
            $exists = (bool)$stmt->fetchColumn();
            $this->tableExistsCache[$table] = $exists;
            return $exists;
        } catch (Throwable $e) {
            $this->tableExistsCache[$table] = false;
            return false;
        }
    }

    private function columnExists($table, $column)
    {
        $table = trim((string)$table);
        $column = trim((string)$column);
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->link->prepare(
                'SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            $exists = (bool)$stmt->fetchColumn();
            $this->columnExistsCache[$cacheKey] = $exists;
            return $exists;
        } catch (Throwable $e) {
            $this->columnExistsCache[$cacheKey] = false;
            return false;
        }
    }

    private function slugify($value)
    {
        $value = trim((string)$value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item';
    }

    private function uniqueSlug($type, $baseSlug, $excludeId = null)
    {
        $baseSlug = $this->slugify($baseSlug);
        $slug = $baseSlug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM {$this->table} WHERE type = ? AND slug = ?";
            $params = [$type, $slug];

            if ($excludeId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = (int)$excludeId;
            }

            $sql .= ' LIMIT 1';
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }

    private function normalizeStatus($status)
    {
        $status = trim((string)$status);
        $allowed = ['draft', 'review', 'approved', 'published', 'archived'];
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function normalizeDateTime($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeType($type)
    {
        $type = trim((string)$type);
        return $this->typeRegistry->isValidKey($type) ? $type : null;
    }

    private function normalizePayloadJson($type, $payload)
    {
        if (!$this->typeRegistry->isValidKey($type)) {
            return null;
        }

        $definition = $this->typeRegistry->getByKey($type);
        $fields = isset($definition['fields']) && is_array($definition['fields'])
            ? $definition['fields']
            : [];

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $normalized = [];
        foreach ($fields as $field) {
            $name = trim((string)($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[$name] = substr(trim((string)($payload[$name] ?? '')), 0, 3000);
        }

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    public function listAll($type, $search = '', $status = '', $page = 1, $perPage = 20)
    {
        if (!$this->tableExists($this->table)) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'pages' => 1,
            ];
        }

        $type = $this->normalizeType($type);
        if ($type === null) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'pages' => 1,
            ];
        }

        $page = max(1, (int)$page);
        $perPage = max(5, min((int)$perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = ['type = ?'];
        $params = [$type];

        $search = trim((string)$search);
        if ($search !== '') {
            $where[] = '(title LIKE ? OR slug LIKE ? OR excerpt LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = trim((string)$status);
        if ($status !== '' && in_array($status, ['draft', 'review', 'approved', 'published', 'archived'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $stmt = $this->link->prepare("SELECT COUNT(*) FROM {$this->table} {$whereSql}");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT * FROM {$this->table} {$whereSql} ORDER BY updated_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ];
        } catch (Throwable $e) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => 1,
            ];
        }
    }

    public function get($id)
    {
        if (!$this->tableExists($this->table)) {
            return null;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function create(array $data, $authorId = null)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Content-items tabel ontbreekt. Draai migratie 011_content_items.sql.');
        }

        $type = $this->normalizeType($data['type'] ?? '');
        if ($type === null) {
            return $this->setLastError('Ongeldig content type.');
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return $this->setLastError('Titel is verplicht.');
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = $this->uniqueSlug($type, $slugInput !== '' ? $slugInput : $title);
        $status = $this->normalizeStatus($data['status'] ?? 'draft');
        $publishedAt = $this->normalizeDateTime($data['published_at'] ?? '');
        $startsAt = $this->normalizeDateTime($data['starts_at'] ?? '');
        $endsAt = $this->normalizeDateTime($data['ends_at'] ?? '');
        $payloadJson = $this->normalizePayloadJson($type, $data['payload_json'] ?? []);

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        try {
            $stmt = $this->link->prepare(
                "INSERT INTO {$this->table}
                 (type, title, slug, excerpt, content, featured_image, payload_json, meta_title, meta_description,
                  status, published_at, starts_at, ends_at, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );

            $ok = $stmt->execute([
                $type,
                $title,
                $slug,
                trim((string)($data['excerpt'] ?? '')) ?: null,
                trim((string)($data['content'] ?? '')) ?: null,
                trim((string)($data['featured_image'] ?? '')) ?: null,
                $payloadJson,
                trim((string)($data['meta_title'] ?? '')) ?: null,
                trim((string)($data['meta_description'] ?? '')) ?: null,
                $status,
                $publishedAt,
                $startsAt,
                $endsAt,
                $authorId ? (int)$authorId : null,
                $authorId ? (int)$authorId : null,
            ]);

            return $ok ? (int)$this->link->lastInsertId() : false;
        } catch (Throwable $e) {
            return $this->setLastError('Item kon niet worden aangemaakt: ' . $e->getMessage());
        }
    }

    public function update($id, array $data, $authorId = null)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Content-items tabel ontbreekt. Draai migratie 011_content_items.sql.');
        }

        $id = (int)$id;
        if ($id <= 0) {
            return $this->setLastError('Ongeldig item.');
        }

        $existing = $this->get($id);
        if (!$existing) {
            return $this->setLastError('Item niet gevonden.');
        }

        $type = $this->normalizeType($data['type'] ?? ($existing['type'] ?? ''));
        if ($type === null) {
            return $this->setLastError('Ongeldig content type.');
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return $this->setLastError('Titel is verplicht.');
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = $this->uniqueSlug($type, $slugInput !== '' ? $slugInput : $title, $id);
        $status = $this->normalizeStatus($data['status'] ?? ($existing['status'] ?? 'draft'));
        $publishedAt = $this->normalizeDateTime($data['published_at'] ?? ($existing['published_at'] ?? ''));
        $startsAt = $this->normalizeDateTime($data['starts_at'] ?? ($existing['starts_at'] ?? ''));
        $endsAt = $this->normalizeDateTime($data['ends_at'] ?? ($existing['ends_at'] ?? ''));
        $payloadJson = $this->normalizePayloadJson($type, $data['payload_json'] ?? ($existing['payload_json'] ?? []));

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = $existing['published_at'] ?: date('Y-m-d H:i:s');
        }

        try {
            $stmt = $this->link->prepare(
                "UPDATE {$this->table}
                 SET type = ?, title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, payload_json = ?,
                     meta_title = ?, meta_description = ?, status = ?, published_at = ?, starts_at = ?, ends_at = ?,
                     updated_by = ?, updated_at = NOW()
                 WHERE id = ?"
            );

            return $stmt->execute([
                $type,
                $title,
                $slug,
                trim((string)($data['excerpt'] ?? '')) ?: null,
                trim((string)($data['content'] ?? '')) ?: null,
                trim((string)($data['featured_image'] ?? '')) ?: null,
                $payloadJson,
                trim((string)($data['meta_title'] ?? '')) ?: null,
                trim((string)($data['meta_description'] ?? '')) ?: null,
                $status,
                $publishedAt,
                $startsAt,
                $endsAt,
                $authorId ? (int)$authorId : null,
                $id,
            ]);
        } catch (Throwable $e) {
            return $this->setLastError('Item kon niet worden bijgewerkt: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Content-items tabel ontbreekt. Draai migratie 011_content_items.sql.');
        }

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        try {
            $stmt = $this->link->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return $this->setLastError('Item kon niet worden verwijderd: ' . $e->getMessage());
        }
    }

    public function listPublishedByType($type, $limit = 50)
    {
        if (!$this->tableExists($this->table)) {
            return [];
        }

        $type = $this->normalizeType($type);
        if ($type === null) {
            return [];
        }

        $limit = max(1, min((int)$limit, 200));

        try {
            $stmt = $this->link->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE type = ?
                   AND status = 'published'
                   AND (published_at IS NULL OR published_at <= NOW())
                 ORDER BY COALESCE(starts_at, published_at, created_at) DESC, id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function findPublishedByTypeAndSlug($type, $slug)
    {
        if (!$this->tableExists($this->table)) {
            return null;
        }

        $type = $this->normalizeType($type);
        if ($type === null) {
            return null;
        }

        $slug = $this->slugify($slug);
        if ($slug === '') {
            return null;
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE type = ?
                   AND slug = ?
                   AND status = 'published'
                   AND (published_at IS NULL OR published_at <= NOW())
                 LIMIT 1"
            );
            $stmt->execute([$type, $slug]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
