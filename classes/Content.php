<?php

class Content
{
    private $link;
    private $tableExistsCache = [];
    private $columnExistsCache = [];
    private $lastError = '';

    public function __construct($link)
    {
        $this->link = $link;
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

    private function uploadErrorMessage($code)
    {
        $code = (int)$code;
        if ($code === UPLOAD_ERR_OK) {
            return '';
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'Bestand is te groot volgens upload-limiet.';
        }
        if ($code === UPLOAD_ERR_PARTIAL) {
            return 'Bestand is maar gedeeltelijk geüpload.';
        }
        if ($code === UPLOAD_ERR_NO_FILE) {
            return 'Geen bestand geselecteerd.';
        }
        if ($code === UPLOAD_ERR_NO_TMP_DIR) {
            return 'Temp-map voor uploads ontbreekt op server.';
        }
        if ($code === UPLOAD_ERR_CANT_WRITE) {
            return 'Server kan uploadbestand niet wegschrijven.';
        }
        if ($code === UPLOAD_ERR_EXTENSION) {
            return 'Upload geblokkeerd door PHP extensie.';
        }
        return 'Onbekende uploadfout.';
    }

    public function listMediaFolders()
    {
        if (!$this->tableExists('media_folders')) {
            return [];
        }

        try {
            $stmt = $this->link->query(
            "SELECT f.id, f.name, f.parent_id, f.created_at, u.username AS created_by_name
             FROM media_folders f
             LEFT JOIN users u ON u.id = f.created_by
             ORDER BY COALESCE(f.parent_id, 0) ASC, f.name ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function createMediaFolder($name, $parentId = null, $createdBy = null)
    {
        if (!$this->tableExists('media_folders')) {
            return false;
        }

        $name = trim((string)$name);
        if ($name === '') {
            return false;
        }

        $parent = null;
        if ($parentId !== null && (int)$parentId > 0) {
            $parent = (int)$parentId;
        }

        try {
            $stmt = $this->link->prepare(
            "INSERT INTO media_folders (name, parent_id, created_by, created_at)
             VALUES (?, ?, ?, NOW())"
            );

            $ok = $stmt->execute([$name, $parent, $createdBy ? (int)$createdBy : null]);
            return $ok ? (int)$this->link->lastInsertId() : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function listMediaItems($search = '', $folderId = null, $page = 1, $perPage = 24, $sort = 'newest')
    {
        if (!$this->tableExists('media_items')) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => max(1, min((int)$perPage, 100)),
                'pages' => 1,
            ];
        }

        $page = max(1, (int)$page);
        $perPage = max(1, min((int)$perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        $search = trim((string)$search);
        if ($search !== '') {
            $like = '%' . strtr($search, ['%' => '\\%', '_' => '\\_']) . '%';
            $where[] = '(m.filename LIKE ? OR COALESCE(m.alt_text,\'\') LIKE ? OR COALESCE(m.path,\'\') LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ((int)$folderId > 0) {
            $where[] = 'm.folder_id = ?';
            $params[] = (int)$folderId;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $countStmt = $this->link->prepare("SELECT COUNT(*) FROM media_items m {$whereSql}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

        $orderSql = 'm.created_at DESC';
        if ($sort === 'oldest') {
            $orderSql = 'm.created_at ASC';
        } elseif ($sort === 'name_asc') {
            $orderSql = 'm.filename ASC';
        } elseif ($sort === 'name_desc') {
            $orderSql = 'm.filename DESC';
        }

        $sql = "SELECT m.*, f.name AS folder_name, u.username AS uploaded_by_name
                FROM media_items m
                LEFT JOIN media_folders f ON f.id = m.folder_id
                LEFT JOIN users u ON u.id = m.uploaded_by
                {$whereSql}
                ORDER BY {$orderSql}
                LIMIT {$perPage} OFFSET {$offset}";

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

    public function uploadMedia(array $file, $folderId = null, $altText = '', $uploadedBy = null)
    {
        $this->clearLastError();

        if (!$this->tableExists('media_items')) {
            return $this->setLastError('Media-tabellen ontbreken. Draai migratie 005_content_workflow.sql.');
        }

        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            return $this->setLastError($this->uploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return $this->setLastError('Uploadbestand is niet geldig (tmp ontbreekt).');
        }

        $originalName = trim((string)($file['name'] ?? 'file'));
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $safeBase = trim($safeBase, '-_');
        if ($safeBase === '') {
            $safeBase = 'media';
        }

        $subDir = 'media/' . date('Y/m');
        $uuid = class_exists('Ramsey\\Uuid\\Uuid')
            ? \Ramsey\Uuid\Uuid::uuid7()->toString()
            : (bin2hex(random_bytes(8)) . '-' . date('YmdHis'));
        $filename = $safeBase . '-' . $uuid . ($ext !== '' ? ('.' . $ext) : '');
        $relativePath = $subDir . '/' . $filename;
        $absoluteUploadsRoot = dirname(__DIR__) . '/public/uploads';
        $targetPath = $absoluteUploadsRoot . '/' . $relativePath;

        $stored = false;
        if (class_exists('League\\Flysystem\\Filesystem') && class_exists('League\\Flysystem\\Local\\LocalFilesystemAdapter')) {
            $stream = null;
            try {
                $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($absoluteUploadsRoot);
                $filesystem = new \League\Flysystem\Filesystem($adapter);
                $stream = fopen((string)$file['tmp_name'], 'rb');
                if ($stream === false) {
                    return $this->setLastError('Uploadbestand kan niet gelezen worden.');
                }

                $filesystem->writeStream($relativePath, $stream, ['visibility' => 'public']);
                $stored = true;
            } catch (Throwable $e) {
                $stored = false;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        if (!$stored) {
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                return $this->setLastError('Uploadmap kon niet worden aangemaakt: ' . $targetDir);
            }

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $this->setLastError('Uploadbestand kon niet worden verplaatst naar uploads-map.');
            }
        }

        if (class_exists('App\\Services\\MediaService')) {
            try {
                $mediaService = new App\Services\MediaService();
                $mediaService->optimizeImage($targetPath);
            } catch (Throwable $e) {
                // Optimization failure should not block successful uploads.
            }
        }

        $publicPath = '/uploads/' . $relativePath;

        $mimeType = '';
        if (class_exists('Symfony\\Component\\Mime\\MimeTypes')) {
            try {
                $mimeType = (string)(\Symfony\Component\Mime\MimeTypes::getDefault()->guessMimeType($targetPath) ?? '');
            } catch (Throwable $e) {
                $mimeType = '';
            }
        }
        if ($mimeType === '') {
            $mimeType = (string)($file['type'] ?? null);
        }
        $sizeBytes = isset($file['size']) ? (int)$file['size'] : null;

        try {
            $stmt = $this->link->prepare(
            "INSERT INTO media_items
             (folder_id, filename, path, mime_type, size_bytes, alt_text, uploaded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );

            $ok = $stmt->execute([
                ((int)$folderId > 0) ? (int)$folderId : null,
                $originalName,
                $publicPath,
                $mimeType !== '' ? $mimeType : null,
                $sizeBytes,
                trim((string)$altText) !== '' ? trim((string)$altText) : null,
                $uploadedBy ? (int)$uploadedBy : null,
            ]);

            if (!$ok) {
                return $this->setLastError('Media record kon niet in database worden opgeslagen.');
            }

            return (int)$this->link->lastInsertId();
        } catch (Throwable $e) {
            return $this->setLastError('Databasefout bij media upload: ' . $e->getMessage());
        }
    }

    public function getMediaItemById($id)
    {
        if (!$this->tableExists('media_items')) {
            return null;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT m.*, f.name AS folder_name, u.username AS uploaded_by_name
                 FROM media_items m
                 LEFT JOIN media_folders f ON f.id = m.folder_id
                 LEFT JOIN users u ON u.id = m.uploaded_by
                 WHERE m.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function updateMediaMeta($id, array $data)
    {
        if (!$this->tableExists('media_items')) {
            return false;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        try {
            $stmt = $this->link->prepare(
            "UPDATE media_items
             SET alt_text = ?, folder_id = ?, crop_x = ?, crop_y = ?, crop_w = ?, crop_h = ?,
                 resize_w = ?, resize_h = ?, updated_at = NOW()
             WHERE id = ?"
            );

            return $stmt->execute([
                trim((string)($data['alt_text'] ?? '')) ?: null,
                ((int)($data['folder_id'] ?? 0) > 0) ? (int)$data['folder_id'] : null,
                $this->toNullableInt($data['crop_x'] ?? null),
                $this->toNullableInt($data['crop_y'] ?? null),
                $this->toNullableInt($data['crop_w'] ?? null),
                $this->toNullableInt($data['crop_h'] ?? null),
                $this->toNullableInt($data['resize_w'] ?? null),
                $this->toNullableInt($data['resize_h'] ?? null),
                $id,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function listMenuItems($location = 'main')
    {
        if (!$this->tableExists('menu_items')) {
            return [];
        }

        $location = trim((string)$location);
        if ($location === '') {
            $location = 'main';
        }

        try {
            $stmt = $this->link->prepare(
            "SELECT m.*, u.username AS created_by_name
             FROM menu_items m
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.location = ?
             ORDER BY m.sort_order ASC, m.id ASC"
            );
            $stmt->execute([$location]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getMenuTree($location = 'main', $activeOnly = false)
    {
        $items = $this->listMenuItems($location);
        if (empty($items)) {
            return [];
        }

        $nodesById = [];
        foreach ($items as $item) {
            if ($activeOnly && empty($item['is_active'])) {
                continue;
            }
            $item['children'] = [];
            $nodesById[(int)$item['id']] = $item;
        }

        $tree = [];
        foreach ($nodesById as $id => $node) {
            $parentId = (int)($node['parent_id'] ?? 0);
            if ($parentId > 0 && isset($nodesById[$parentId])) {
                $nodesById[$parentId]['children'][] = $node;
            } else {
                $tree[] = $node;
            }
        }

        $sortFn = function (&$nodes) use (&$sortFn) {
            usort($nodes, function ($a, $b) {
                $orderA = (int)($a['sort_order'] ?? 0);
                $orderB = (int)($b['sort_order'] ?? 0);
                if ($orderA === $orderB) {
                    return ((int)$a['id'] <=> (int)$b['id']);
                }
                return $orderA <=> $orderB;
            });

            foreach ($nodes as &$child) {
                if (!empty($child['children'])) {
                    $sortFn($child['children']);
                }
            }
        };

        $sortFn($tree);
        return $tree;
    }

    public function saveMenuItem(array $data, $userId = null)
    {
        if (!$this->tableExists('menu_items')) {
            return false;
        }

        $hasParentColumn = $this->columnExists('menu_items', 'parent_id');

        $id = (int)($data['id'] ?? 0);
        $location = trim((string)($data['location'] ?? 'main'));
        $label = trim((string)($data['label'] ?? ''));
        $url = trim((string)($data['url'] ?? ''));
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $parentId = (int)($data['parent_id'] ?? 0);
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($label === '' || $url === '') {
            return false;
        }

        if ($parentId < 0) {
            $parentId = 0;
        }

        if ($hasParentColumn && $parentId > 0) {
            try {
                $parentStmt = $this->link->prepare('SELECT id, location FROM menu_items WHERE id = ? LIMIT 1');
                $parentStmt->execute([$parentId]);
                $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
                if (!$parent || ($parent['location'] ?? '') !== $location) {
                    return false;
                }
                if ($id > 0 && (int)$parent['id'] === $id) {
                    return false;
                }
            } catch (Throwable $e) {
                return false;
            }
        }

        if ($id > 0) {
            try {
                if ($hasParentColumn) {
                    $stmt = $this->link->prepare(
                        "UPDATE menu_items
                         SET parent_id = ?, location = ?, label = ?, url = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                         WHERE id = ?"
                    );
                    return $stmt->execute([$parentId > 0 ? $parentId : null, $location, $label, $url, $sortOrder, $isActive, $id]);
                }

                $stmt = $this->link->prepare(
                    "UPDATE menu_items
                     SET location = ?, label = ?, url = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                return $stmt->execute([$location, $label, $url, $sortOrder, $isActive, $id]);
            } catch (Throwable $e) {
                return false;
            }
        }

        try {
            if ($hasParentColumn) {
                $stmt = $this->link->prepare(
                    "INSERT INTO menu_items (parent_id, location, label, url, sort_order, is_active, created_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                return $stmt->execute([$parentId > 0 ? $parentId : null, $location, $label, $url, $sortOrder, $isActive, $userId ? (int)$userId : null]);
            }

            $stmt = $this->link->prepare(
                "INSERT INTO menu_items (location, label, url, sort_order, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            return $stmt->execute([$location, $label, $url, $sortOrder, $isActive, $userId ? (int)$userId : null]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function reorderMenuTree($location, array $tree, $userId = null)
    {
        if (!$this->tableExists('menu_items')) {
            return false;
        }

        if (!$this->columnExists('menu_items', 'parent_id')) {
            return false;
        }

        $location = trim((string)$location);
        if ($location === '') {
            $location = 'main';
        }

        try {
            $stmt = $this->link->prepare('SELECT id FROM menu_items WHERE location = ?');
            $stmt->execute([$location]);
            $existingIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $allowed = array_fill_keys($existingIds, true);
            $seen = [];
            $updates = [];

            $walk = function ($nodes, $parentId) use (&$walk, &$updates, &$seen, $allowed) {
                if (!is_array($nodes)) {
                    return false;
                }

                foreach (array_values($nodes) as $index => $node) {
                    if (!is_array($node)) {
                        return false;
                    }

                    $id = (int)($node['id'] ?? 0);
                    if ($id <= 0 || !isset($allowed[$id]) || isset($seen[$id])) {
                        return false;
                    }

                    $seen[$id] = true;
                    $updates[] = [
                        'id' => $id,
                        'parent_id' => $parentId,
                        'sort_order' => ($index + 1) * 10,
                    ];

                    $children = $node['children'] ?? [];
                    if (!is_array($children)) {
                        return false;
                    }
                    if (!$walk($children, $id)) {
                        return false;
                    }
                }

                return true;
            };

            if (!$walk($tree, null)) {
                return false;
            }

            if (count($seen) !== count($existingIds)) {
                return false;
            }

            $this->link->beginTransaction();
            $updateStmt = $this->link->prepare(
                'UPDATE menu_items SET parent_id = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND location = ?'
            );

            foreach ($updates as $update) {
                $updateStmt->execute([
                    $update['parent_id'],
                    $update['sort_order'],
                    $update['id'],
                    $location,
                ]);
            }

            $this->link->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->link->inTransaction()) {
                $this->link->rollBack();
            }
            return false;
        }
    }

    public function deleteMenuItem($id)
    {
        if (!$this->tableExists('menu_items')) {
            return false;
        }

        try {
            $stmt = $this->link->prepare("DELETE FROM menu_items WHERE id = ?");
            return $stmt->execute([(int)$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function tableExists($tableName)
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?
                 LIMIT 1"
            );
            $stmt->execute([$tableName]);
            $exists = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $exists = false;
        }

        $this->tableExistsCache[$tableName] = $exists;
        return $exists;
    }

    private function columnExists($tableName, $columnName)
    {
        $tableName = trim((string)$tableName);
        $columnName = trim((string)$columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                 LIMIT 1"
            );
            $stmt->execute([$tableName, $columnName]);
            $exists = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $exists = false;
        }

        $this->columnExistsCache[$cacheKey] = $exists;
        return $exists;
    }

    private function toNullableInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }
}
