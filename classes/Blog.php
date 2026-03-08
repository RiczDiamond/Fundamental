<?php

class Blog {

    private $link;
    private $table = 'blogs';
    private $tableExistsCache = [];
    private $columnExistsCache = [];
    private $lastError = '';

    public function __construct($link)
    {
        $this->link = $link;
    }

    public function slugify($value)
    {
        $value = trim((string)$value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'post';
    }

    public function makePermalink($slug)
    {
        return '/blog/' . ltrim($this->slugify($slug), '/');
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

    private function uniqueSlug($baseSlug, $excludeId = null)
    {
        $baseSlug = $this->slugify($baseSlug);
        $slug = $baseSlug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM {$this->table} WHERE slug = ?";
            $params = [$slug];

            if ($excludeId !== null) {
                $sql .= " AND id <> ?";
                $params[] = (int)$excludeId;
            }

            $sql .= " LIMIT 1";
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
        $allowed = ['draft', 'published', 'scheduled', 'archived'];
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

    private function isAllowedLink($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $value)) {
            return true;
        }

        return false;
    }

    private function validateBuilderJson($rawBuilderJson, &$normalizedJson, &$error)
    {
        $normalizedJson = null;
        $error = '';

        if ($rawBuilderJson === null || trim((string)$rawBuilderJson) === '') {
            return true;
        }

        if (!is_string($rawBuilderJson)) {
            $error = 'verwachte JSON-string';
            return false;
        }

        $decoded = json_decode($rawBuilderJson, true);
        if (!is_array($decoded)) {
            $error = 'JSON moet een array van blocks zijn';
            return false;
        }

        if (count($decoded) > 120) {
            $error = 'te veel blocks (max 120)';
            return false;
        }

        $normalizedBlocks = [];
        foreach (array_values($decoded) as $index => $block) {
            if (!is_array($block)) {
                $error = 'block #' . ($index + 1) . ' is geen object';
                return false;
            }

            $type = trim((string)($block['type'] ?? ''));
            if ($type === '') {
                $error = 'block #' . ($index + 1) . ' mist type';
                return false;
            }

            $type = strtolower($type);
            $data = (isset($block['data']) && is_array($block['data'])) ? $block['data'] : $block;
            unset($data['type'], $data['id']);

            $normalizedBlock = ['type' => $type, 'data' => []];
            if (!empty($block['id'])) {
                $normalizedBlock['id'] = substr(trim((string)$block['id']), 0, 80);
            }

            if ($type === 'hero') {
                $title = trim((string)($data['title'] ?? ''));
                if ($title === '') {
                    $error = 'hero block #' . ($index + 1) . ' vereist title';
                    return false;
                }
                $normalizedBlock['data']['title'] = substr($title, 0, 180);

                if (isset($data['subtitle'])) {
                    $normalizedBlock['data']['subtitle'] = substr(trim((string)$data['subtitle']), 0, 400);
                }

                $ctaLabel = trim((string)($data['cta_label'] ?? ''));
                $ctaUrl = trim((string)($data['cta_url'] ?? ''));
                if ($ctaLabel !== '' || $ctaUrl !== '') {
                    if ($ctaLabel === '' || $ctaUrl === '') {
                        $error = 'hero block #' . ($index + 1) . ' vereist cta_label én cta_url samen';
                        return false;
                    }
                    if (!$this->isAllowedLink($ctaUrl)) {
                        $error = 'hero block #' . ($index + 1) . ' heeft ongeldige cta_url';
                        return false;
                    }
                    $normalizedBlock['data']['cta_label'] = substr($ctaLabel, 0, 120);
                    $normalizedBlock['data']['cta_url'] = substr($ctaUrl, 0, 255);
                }
            } elseif ($type === 'text') {
                $content = trim((string)($data['content'] ?? ''));
                if ($content === '') {
                    $error = 'text block #' . ($index + 1) . ' vereist content';
                    return false;
                }
                $normalizedBlock['data']['content'] = $content;
            } elseif ($type === 'image') {
                $src = trim((string)($data['src'] ?? ''));
                if (!$this->isAllowedLink($src)) {
                    $error = 'image block #' . ($index + 1) . ' vereist geldige src';
                    return false;
                }
                $normalizedBlock['data']['src'] = substr($src, 0, 255);
                $normalizedBlock['data']['alt'] = substr(trim((string)($data['alt'] ?? '')), 0, 255);
                if (!empty($data['caption'])) {
                    $normalizedBlock['data']['caption'] = substr(trim((string)$data['caption']), 0, 300);
                }
            } elseif ($type === 'gallery') {
                $images = $data['images'] ?? null;
                if (!is_array($images) || empty($images)) {
                    $error = 'gallery block #' . ($index + 1) . ' vereist images[]';
                    return false;
                }
                if (count($images) > 24) {
                    $error = 'gallery block #' . ($index + 1) . ' max 24 images';
                    return false;
                }
                $normalizedImages = [];
                foreach (array_values($images) as $imgIndex => $img) {
                    if (!is_array($img)) {
                        $error = 'gallery block #' . ($index + 1) . ' image #' . ($imgIndex + 1) . ' is ongeldig';
                        return false;
                    }
                    $src = trim((string)($img['src'] ?? ''));
                    if (!$this->isAllowedLink($src)) {
                        $error = 'gallery block #' . ($index + 1) . ' image #' . ($imgIndex + 1) . ' vereist geldige src';
                        return false;
                    }
                    $normalizedImages[] = [
                        'src' => substr($src, 0, 255),
                        'alt' => substr(trim((string)($img['alt'] ?? '')), 0, 255),
                    ];
                }
                $normalizedBlock['data']['images'] = $normalizedImages;
            } elseif ($type === 'cta') {
                $label = trim((string)($data['label'] ?? ''));
                $url = trim((string)($data['url'] ?? ''));
                if ($label === '' || !$this->isAllowedLink($url)) {
                    $error = 'cta block #' . ($index + 1) . ' vereist label en geldige url';
                    return false;
                }
                $normalizedBlock['data']['label'] = substr($label, 0, 120);
                $normalizedBlock['data']['url'] = substr($url, 0, 255);
                $style = strtolower(trim((string)($data['style'] ?? 'primary')));
                if (!in_array($style, ['primary', 'secondary'], true)) {
                    $style = 'primary';
                }
                $normalizedBlock['data']['style'] = $style;
            } elseif ($type === 'quote') {
                $quote = trim((string)($data['quote'] ?? ''));
                if ($quote === '') {
                    $error = 'quote block #' . ($index + 1) . ' vereist quote';
                    return false;
                }
                $normalizedBlock['data']['quote'] = substr($quote, 0, 600);
                if (!empty($data['author'])) {
                    $normalizedBlock['data']['author'] = substr(trim((string)$data['author']), 0, 120);
                }
            } elseif ($type === 'columns') {
                $columns = $data['columns'] ?? null;
                if (!is_array($columns) || count($columns) < 2 || count($columns) > 4) {
                    $error = 'columns block #' . ($index + 1) . ' vereist 2-4 kolommen';
                    return false;
                }
                $normalizedColumns = [];
                foreach (array_values($columns) as $colIndex => $column) {
                    if (!is_array($column)) {
                        $error = 'columns block #' . ($index + 1) . ' kolom #' . ($colIndex + 1) . ' is ongeldig';
                        return false;
                    }
                    $content = trim((string)($column['content'] ?? ''));
                    if ($content === '') {
                        $error = 'columns block #' . ($index + 1) . ' kolom #' . ($colIndex + 1) . ' vereist content';
                        return false;
                    }
                    $normalizedColumns[] = [
                        'heading' => substr(trim((string)($column['heading'] ?? '')), 0, 120),
                        'content' => $content,
                    ];
                }
                $normalizedBlock['data']['columns'] = $normalizedColumns;
            } elseif ($type === 'spacer') {
                $size = strtolower(trim((string)($data['size'] ?? 'md')));
                if (!in_array($size, ['sm', 'md', 'lg', 'xl'], true)) {
                    $size = 'md';
                }
                $normalizedBlock['data']['size'] = $size;
            } else {
                $error = 'onbekend block type in block #' . ($index + 1) . ': ' . $type;
                return false;
            }

            $normalizedBlocks[] = $normalizedBlock;
        }

        $encoded = json_encode($normalizedBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $error = 'kon builder schema niet serialiseren';
            return false;
        }

        $normalizedJson = $encoded;
        return true;
    }

    private function buildRevisionPayload(array $row)
    {
        return [
            'title' => $row['title'] ?? '',
            'slug' => $row['slug'] ?? '',
            'featured_image' => $row['featured_image'] ?? null,
            'intro' => $row['intro'] ?? null,
            'category' => $row['category'] ?? null,
            'tags' => $this->normalizeTags($row['tags'] ?? null),
            'meta_title' => $row['meta_title'] ?? null,
            'meta_description' => $row['meta_description'] ?? null,
            'og_image' => $row['og_image'] ?? null,
            'excerpt' => $row['excerpt'] ?? null,
            'content' => $row['content'] ?? null,
            'status' => $this->normalizeStatus($row['status'] ?? 'draft'),
            'published_at' => $this->normalizeDateTime($row['published_at'] ?? null),
            'scheduled_at' => $this->normalizeDateTime($row['scheduled_at'] ?? null),
        ];
    }

    public function saveRevision($blogId, $editorId = null)
    {
        if (!$this->tableExists('blog_revisions')) {
            return false;
        }

        $blogId = (int)$blogId;
        if ($blogId <= 0) {
            return false;
        }

        $row = $this->get($blogId);
        if (!$row) {
            return false;
        }

        $payload = $this->buildRevisionPayload($row);
        try {
            $stmt = $this->link->prepare(
                "INSERT INTO blog_revisions
                 (blog_id, editor_id, title, slug, featured_image, intro, category, tags,
                   meta_title, meta_description, og_image, excerpt, content,
                  status, published_at, scheduled_at, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            return $stmt->execute([
                $blogId,
                $editorId ? (int)$editorId : null,
                $payload['title'],
                $payload['slug'],
                $payload['featured_image'],
                $payload['intro'],
                $payload['category'],
                $payload['tags'],
                $payload['meta_title'],
                $payload['meta_description'],
                $payload['og_image'],
                $payload['excerpt'],
                $payload['content'],
                $payload['status'],
                $payload['published_at'],
                $payload['scheduled_at'],
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function create($data, $authorId = null)
    {
        $this->clearLastError();

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return false;
        }

        $slugSource = $data['slug'] ?? $title;
        $slug = $this->uniqueSlug($slugSource);
        $status = $this->normalizeStatus($data['status'] ?? 'draft');

        $publishedAt = null;
        $scheduledAt = $this->normalizeDateTime($data['scheduled_at'] ?? null);

        if ($status === 'scheduled' && empty($scheduledAt)) {
            $status = 'draft';
        }
        if ($status === 'published') {
            $publishedAt = $this->normalizeDateTime($data['published_at'] ?? null) ?: date('Y-m-d H:i:s');
            $scheduledAt = null;
        }

        $useWorkflowColumns = $this->columnExists($this->table, 'scheduled_at');

        try {
            if ($useWorkflowColumns) {
                $stmt = $this->link->prepare(
                    "INSERT INTO {$this->table}
                     (title, slug, permalink, featured_image, intro, category, tags, meta_title, meta_description, og_image,
                      excerpt, content, status, author_id, published_at, scheduled_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );

                $ok = $stmt->execute([
                    $title,
                    $slug,
                    $this->makePermalink($slug),
                    $data['featured_image'] ?? null,
                    $data['intro'] ?? ($data['excerpt'] ?? null),
                    $data['category'] ?? null,
                    $this->normalizeTags($data['tags'] ?? null),
                    $data['meta_title'] ?? null,
                    $data['meta_description'] ?? null,
                    $data['og_image'] ?? ($data['featured_image'] ?? null),
                    $data['excerpt'] ?? null,
                    $data['content'] ?? null,
                    $status,
                    $authorId,
                    $publishedAt,
                    $scheduledAt,
                ]);
            } else {
                $stmt = $this->link->prepare(
                    "INSERT INTO {$this->table}
                     (title, slug, permalink, featured_image, intro, category, tags, meta_title, meta_description, og_image,
                      excerpt, content, status, author_id, published_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );

                $ok = $stmt->execute([
                    $title,
                    $slug,
                    $this->makePermalink($slug),
                    $data['featured_image'] ?? null,
                    $data['intro'] ?? ($data['excerpt'] ?? null),
                    $data['category'] ?? null,
                    $this->normalizeTags($data['tags'] ?? null),
                    $data['meta_title'] ?? null,
                    $data['meta_description'] ?? null,
                    $data['og_image'] ?? ($data['featured_image'] ?? null),
                    $data['excerpt'] ?? null,
                    $data['content'] ?? null,
                    $status,
                    $authorId,
                    $publishedAt,
                ]);
            }
        } catch (Throwable $e) {
            return false;
        }

        if (!$ok) {
            return false;
        }

        $newId = (int)$this->link->lastInsertId();
        $this->saveRevision($newId, $authorId);
        return $newId;
    }

    public function update($id, $data, $editorId = null)
    {
        $this->clearLastError();

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        $current = $this->get($id);
        if (!$current) {
            return false;
        }

        $title = trim($data['title'] ?? $current['title']);
        if ($title === '') {
            return false;
        }

        $slugInput = $data['slug'] ?? $current['slug'] ?? $title;
        $slug = $this->uniqueSlug($slugInput, $id);

        $status = $this->normalizeStatus($data['status'] ?? $current['status']);

        $publishedAt = $this->normalizeDateTime($data['published_at'] ?? $current['published_at']);
        $scheduledAt = $this->normalizeDateTime($data['scheduled_at'] ?? $current['scheduled_at']);
        if ($status === 'scheduled' && empty($scheduledAt)) {
            $status = 'draft';
        }
        if ($status === 'published' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
            $scheduledAt = null;
        }
        if ($status !== 'published') {
            if ($status === 'scheduled') {
                $publishedAt = null;
            } elseif ($status === 'draft' || $status === 'archived') {
                $publishedAt = null;
                $scheduledAt = null;
            }
        }

        $useWorkflowColumns = $this->columnExists($this->table, 'scheduled_at');

        try {
            if ($useWorkflowColumns) {
                $stmt = $this->link->prepare(
                    "UPDATE {$this->table}
                     SET title = ?, slug = ?, permalink = ?, featured_image = ?, intro = ?, category = ?, tags = ?,
                         meta_title = ?, meta_description = ?, og_image = ?, excerpt = ?, content = ?,
                         status = ?, published_at = ?, scheduled_at = ?, updated_at = NOW()
                     WHERE id = ?"
                );

                $ok = $stmt->execute([
                    $title,
                    $slug,
                    $this->makePermalink($slug),
                    $data['featured_image'] ?? $current['featured_image'],
                    $data['intro'] ?? $current['intro'],
                    $data['category'] ?? $current['category'],
                    $this->normalizeTags($data['tags'] ?? $current['tags']),
                    $data['meta_title'] ?? $current['meta_title'],
                    $data['meta_description'] ?? $current['meta_description'],
                    $data['og_image'] ?? $current['og_image'],
                    $data['excerpt'] ?? $current['excerpt'],
                    $data['content'] ?? $current['content'],
                    $status,
                    $publishedAt,
                    $scheduledAt,
                    $id,
                ]);
            } else {
                $stmt = $this->link->prepare(
                    "UPDATE {$this->table}
                     SET title = ?, slug = ?, permalink = ?, featured_image = ?, intro = ?, category = ?, tags = ?,
                         meta_title = ?, meta_description = ?, og_image = ?, excerpt = ?, content = ?,
                         status = ?, published_at = ?, updated_at = NOW()
                     WHERE id = ?"
                );

                $ok = $stmt->execute([
                    $title,
                    $slug,
                    $this->makePermalink($slug),
                    $data['featured_image'] ?? $current['featured_image'],
                    $data['intro'] ?? $current['intro'],
                    $data['category'] ?? $current['category'],
                    $this->normalizeTags($data['tags'] ?? $current['tags']),
                    $data['meta_title'] ?? $current['meta_title'],
                    $data['meta_description'] ?? $current['meta_description'],
                    $data['og_image'] ?? $current['og_image'],
                    $data['excerpt'] ?? $current['excerpt'],
                    $data['content'] ?? $current['content'],
                    $status,
                    $publishedAt,
                    $id,
                ]);
            }
        } catch (Throwable $e) {
            return false;
        }

        if ($ok) {
            $this->saveRevision($id, $editorId);
        }
        return $ok;
    }

    public function delete($id)
    {
        $stmt = $this->link->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function get($id)
    {
        $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listAll($search = '')
    {
        $result = $this->listAllAdvanced([
            'search' => $search,
            'page' => 1,
            'per_page' => 1000,
        ]);
        return $result['items'];
    }

    public function listAllAdvanced(array $options = [])
    {
        $search = trim((string)($options['search'] ?? ''));
        $status = trim((string)($options['status'] ?? ''));
        $category = trim((string)($options['category'] ?? ''));
        $sort = trim((string)($options['sort'] ?? 'newest'));
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, min((int)($options['per_page'] ?? 10), 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . strtr($search, ['%' => '\\%', '_' => '\\_']) . '%';
            $where[] = "(b.title LIKE ? OR b.slug LIKE ? OR b.status LIKE ? OR COALESCE(b.category,'') LIKE ? OR COALESCE(b.tags,'') LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        if ($status !== '' && in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }

        if ($category !== '') {
            $where[] = 'b.category = ?';
            $params[] = $category;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->link->prepare("SELECT COUNT(*) FROM {$this->table} b {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $orderBy = 'b.created_at DESC';
        if ($sort === 'oldest') {
            $orderBy = 'b.created_at ASC';
        } elseif ($sort === 'updated') {
            $orderBy = 'b.updated_at DESC';
        } elseif ($sort === 'title_asc') {
            $orderBy = 'b.title ASC';
        } elseif ($sort === 'title_desc') {
            $orderBy = 'b.title DESC';
        } elseif ($sort === 'status') {
            $orderBy = 'b.status ASC, b.updated_at DESC';
        }

        $stmt = $this->link->prepare(
            "SELECT b.*, u.username AS author_name, u.display_name AS author_display_name
             FROM {$this->table} b
             LEFT JOIN users u ON u.id = b.author_id
             {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function listPublished($limit = 20, $category = null)
    {
        $limit = max(1, min((int)$limit, 100));
        $sql = "SELECT b.id, b.title, b.slug, b.permalink, b.featured_image, b.intro, b.category, b.tags,
                       b.excerpt, b.content, b.author_id, b.published_at, b.created_at, b.updated_at,
                       b.meta_title, b.meta_description, b.og_image, b.view_count, b.like_count,
                       u.username AS author_name, u.display_name AS author_display_name
                FROM {$this->table} b
                LEFT JOIN users u ON u.id = b.author_id
                WHERE b.status = 'published'";
        $params = [];
        if (!empty($category)) {
            $sql .= " AND b.category = ?";
            $params[] = (string)$category;
        }
        $sql .= " ORDER BY COALESCE(b.published_at, b.created_at) DESC LIMIT {$limit}";
        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPublishedBySlug($slug)
    {
        $slug = $this->slugify($slug);
        $stmt = $this->link->prepare(
            "SELECT b.*, u.username AS author_name, u.display_name AS author_display_name
             FROM {$this->table} b
             LEFT JOIN users u ON u.id = b.author_id
             WHERE b.slug = ? AND b.status = 'published' LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findBySlug($slug)
    {
        $slug = $this->slugify($slug);
        $stmt = $this->link->prepare(
            "SELECT b.*, u.username AS author_name, u.display_name AS author_display_name
             FROM {$this->table} b
             LEFT JOIN users u ON u.id = b.author_id
             WHERE b.slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findById($id)
    {
        return $this->get((int)$id);
    }

    public function findPublishedByPermalink($permalink)
    {
        $normalized = '/' . ltrim((string)$permalink, '/');
        $stmt = $this->link->prepare(
            "SELECT * FROM {$this->table} WHERE permalink = ? AND status = 'published' LIMIT 1"
        );
        $stmt->execute([$normalized]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function incrementViewCount($id)
    {
        $stmt = $this->link->prepare("UPDATE {$this->table} SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function getPreviousPost($current)
    {
        $publishedAt = $current['published_at'] ?? $current['created_at'];
        $id = (int)($current['id'] ?? 0);
        $stmt = $this->link->prepare(
            "SELECT id, title, slug, permalink
             FROM {$this->table}
             WHERE status = 'published'
               AND (COALESCE(published_at, created_at) < ? OR (COALESCE(published_at, created_at) = ? AND id < ?))
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$publishedAt, $publishedAt, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getNextPost($current)
    {
        $publishedAt = $current['published_at'] ?? $current['created_at'];
        $id = (int)($current['id'] ?? 0);
        $stmt = $this->link->prepare(
            "SELECT id, title, slug, permalink
             FROM {$this->table}
             WHERE status = 'published'
               AND (COALESCE(published_at, created_at) > ? OR (COALESCE(published_at, created_at) = ? AND id > ?))
             ORDER BY COALESCE(published_at, created_at) ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$publishedAt, $publishedAt, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getRelatedPosts($current, $limit = 3)
    {
        $limit = max(1, min((int)$limit, 12));
        $id = (int)($current['id'] ?? 0);
        $category = trim((string)($current['category'] ?? ''));
        $tags = $this->normalizeTags($current['tags'] ?? '');

        if ($category !== '') {
            $stmt = $this->link->prepare(
                "SELECT id, title, slug, permalink, excerpt, category
                 FROM {$this->table}
                 WHERE status = 'published' AND id <> ? AND category = ?
                 ORDER BY COALESCE(published_at, created_at) DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$id, $category]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        }

        if ($tags !== '') {
            $firstTag = explode(',', $tags)[0];
            $stmt = $this->link->prepare(
                "SELECT id, title, slug, permalink, excerpt, category
                 FROM {$this->table}
                 WHERE status = 'published' AND id <> ? AND tags LIKE ?
                 ORDER BY COALESCE(published_at, created_at) DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$id, '%' . $firstTag . '%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        }

        $stmt = $this->link->prepare(
            "SELECT id, title, slug, permalink, excerpt, category
             FROM {$this->table}
             WHERE status = 'published' AND id <> ?
             ORDER BY COALESCE(published_at, created_at) DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function estimateReadMinutes($content)
    {
        $text = trim(strip_tags((string)$content));
        if ($text === '') {
            return 1;
        }
        $words = str_word_count($text);
        return max(1, (int)ceil($words / 220));
    }

    public function getComments($blogId, $limit = 200)
    {
        $limit = max(1, min((int)$limit, 500));
        $stmt = $this->link->prepare(
            "SELECT id, blog_id, user_id, author_name, author_email, comment, created_at
             FROM blog_comments
             WHERE blog_id = ? AND status = 'approved'
             ORDER BY created_at ASC
             LIMIT {$limit}"
        );
        $stmt->execute([(int)$blogId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addComment($blogId, $authorName, $authorEmail, $comment, $userId = null)
    {
        $authorName = trim((string)$authorName);
        $authorEmail = trim((string)$authorEmail);
        $comment = trim((string)$comment);

        if ($authorName === '' || $comment === '') {
            return false;
        }

        $stmt = $this->link->prepare(
            "INSERT INTO blog_comments (blog_id, user_id, author_name, author_email, comment, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())"
        );

        return $stmt->execute([
            (int)$blogId,
            $userId,
            $authorName,
            $authorEmail !== '' ? $authorEmail : null,
            $comment,
        ]);
    }

    public function hasLiked($blogId, $sessionKey)
    {
        $stmt = $this->link->prepare("SELECT 1 FROM blog_likes WHERE blog_id = ? AND session_key = ? LIMIT 1");
        $stmt->execute([(int)$blogId, (string)$sessionKey]);
        return (bool)$stmt->fetchColumn();
    }

    public function addLike($blogId, $sessionKey, $userId = null)
    {
        $stmt = $this->link->prepare(
            "INSERT IGNORE INTO blog_likes (blog_id, user_id, session_key, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $ok = $stmt->execute([(int)$blogId, $userId, (string)$sessionKey]);
        if (!$ok) {
            return false;
        }

        $this->link->prepare(
            "UPDATE {$this->table} b
             SET b.like_count = (SELECT COUNT(*) FROM blog_likes bl WHERE bl.blog_id = b.id)
             WHERE b.id = ?"
        )->execute([(int)$blogId]);

        return true;
    }

    public function getCategoryOverview($limit = 50)
    {
        $limit = max(1, min((int)$limit, 200));
        $stmt = $this->link->prepare(
            "SELECT category, COUNT(*) AS total
             FROM {$this->table}
             WHERE status = 'published' AND category IS NOT NULL AND category <> ''
             GROUP BY category
             ORDER BY total DESC, category ASC
             LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistinctCategories($limit = 200)
    {
        $limit = max(1, min((int)$limit, 500));
        $stmt = $this->link->query(
            "SELECT DISTINCT category
             FROM {$this->table}
             WHERE category IS NOT NULL AND category <> ''
             ORDER BY category ASC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function duplicate($id, $authorId = null)
    {
        $source = $this->get((int)$id);
        if (!$source) {
            return false;
        }

        $newTitle = trim((string)($source['title'] ?? ''));
        $newTitle = $newTitle !== '' ? ($newTitle . ' (kopie)') : 'Kopie';

        return $this->create([
            'title' => $newTitle,
            'slug' => ($source['slug'] ?? 'post') . '-kopie',
            'featured_image' => $source['featured_image'] ?? null,
            'intro' => $source['intro'] ?? null,
            'category' => $source['category'] ?? null,
            'tags' => $source['tags'] ?? null,
            'meta_title' => $source['meta_title'] ?? null,
            'meta_description' => $source['meta_description'] ?? null,
            'og_image' => $source['og_image'] ?? null,
            'excerpt' => $source['excerpt'] ?? null,
            'content' => $source['content'] ?? null,
            'status' => 'draft',
        ], $authorId);
    }

    public function bulkUpdateStatus(array $ids, $status, $editorId = null)
    {
        $status = $this->normalizeStatus($status);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($value) {
            return $value > 0;
        })));

        if (empty($ids)) {
            return 0;
        }

        $this->link->beginTransaction();
        try {
            $setPublishedAtSql = ', published_at = NULL';
            $setScheduledSql = ', scheduled_at = NULL';

            if ($status === 'published') {
                $setPublishedAtSql = ', published_at = COALESCE(published_at, NOW())';
            } elseif ($status === 'scheduled') {
                $setScheduledSql = ', scheduled_at = COALESCE(scheduled_at, DATE_ADD(NOW(), INTERVAL 1 HOUR))';
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE {$this->table} SET status = ? {$setPublishedAtSql} {$setScheduledSql}, updated_at = NOW() WHERE id IN ({$placeholders})";
            $stmt = $this->link->prepare($sql);
            $stmt->execute(array_merge([$status], $ids));

            foreach ($ids as $id) {
                $this->saveRevision($id, $editorId);
            }

            $this->link->commit();
            return count($ids);
        } catch (Throwable $e) {
            $this->link->rollBack();
            return 0;
        }
    }

    public function bulkDelete(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($value) {
            return $value > 0;
        })));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->link->prepare("DELETE FROM {$this->table} WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public function publishScheduled()
    {
        if (!$this->columnExists($this->table, 'scheduled_at')) {
            return 0;
        }

        try {
            $stmt = $this->link->prepare(
                "UPDATE {$this->table}
                 SET status = 'published', published_at = COALESCE(published_at, scheduled_at, NOW()), updated_at = NOW()
                 WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
            );
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function listRevisions($blogId, $limit = 30)
    {
        if (!$this->tableExists('blog_revisions')) {
            return [];
        }

        $limit = max(1, min((int)$limit, 200));
        try {
            $stmt = $this->link->prepare(
                "SELECT r.*, u.username AS editor_name
                 FROM blog_revisions r
                 LEFT JOIN users u ON u.id = r.editor_id
                 WHERE r.blog_id = ?
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([(int)$blogId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getRevision($revisionId)
    {
        if (!$this->tableExists('blog_revisions')) {
            return null;
        }

        try {
            $stmt = $this->link->prepare("SELECT * FROM blog_revisions WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$revisionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function restoreRevision($revisionId, $editorId = null)
    {
        $this->clearLastError();

        $revision = $this->getRevision((int)$revisionId);
        if (!$revision) {
            return false;
        }

        $blogId = (int)$revision['blog_id'];
        $current = $this->get($blogId);
        if (!$current) {
            return false;
        }

        $slug = $this->uniqueSlug($revision['slug'] ?? $current['slug'], $blogId);

        $stmt = $this->link->prepare(
            "UPDATE {$this->table}
             SET title = ?, slug = ?, permalink = ?, featured_image = ?, intro = ?, category = ?, tags = ?,
                 meta_title = ?, meta_description = ?, og_image = ?, excerpt = ?, content = ?,
                 status = ?, published_at = ?, scheduled_at = ?, updated_at = NOW()
             WHERE id = ?"
        );

        $ok = $stmt->execute([
            $revision['title'] ?? $current['title'],
            $slug,
            $this->makePermalink($slug),
            $revision['featured_image'] ?? $current['featured_image'],
            $revision['intro'] ?? $current['intro'],
            $revision['category'] ?? $current['category'],
            $this->normalizeTags($revision['tags'] ?? $current['tags']),
            $revision['meta_title'] ?? $current['meta_title'],
            $revision['meta_description'] ?? $current['meta_description'],
            $revision['og_image'] ?? $current['og_image'],
            $revision['excerpt'] ?? $current['excerpt'],
            $revision['content'] ?? $current['content'],
            $this->normalizeStatus($revision['status'] ?? $current['status']),
            $this->normalizeDateTime($revision['published_at'] ?? $current['published_at']),
            $this->normalizeDateTime($revision['scheduled_at'] ?? $current['scheduled_at']),
            $blogId,
        ]);

        if ($ok) {
            $this->saveRevision($blogId, $editorId);
        }

        return $ok;
    }

    public function saveAutosave($blogId, $editorId, array $payload)
    {
        $this->clearLastError();

        if (!$this->tableExists('blog_autosaves')) {
            return false;
        }

        $blogId = (int)$blogId;
        $editorId = (int)$editorId;
        if ($blogId <= 0 || $editorId <= 0) {
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        try {
            $stmt = $this->link->prepare(
                "INSERT INTO blog_autosaves (blog_id, editor_id, payload_json, created_at)
                 VALUES (?, ?, ?, NOW())"
            );

            $ok = $stmt->execute([$blogId, $editorId, $json]);
            if ($ok && $this->columnExists($this->table, 'last_autosaved_at')) {
                $this->link->prepare("UPDATE {$this->table} SET last_autosaved_at = NOW() WHERE id = ?")->execute([$blogId]);
            }

            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getLatestAutosave($blogId, $editorId = null)
    {
        if (!$this->tableExists('blog_autosaves')) {
            return null;
        }

        $sql = "SELECT * FROM blog_autosaves WHERE blog_id = ?";
        $params = [(int)$blogId];
        if ((int)$editorId > 0) {
            $sql .= ' AND editor_id = ?';
            $params[] = (int)$editorId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1';
        try {
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $decoded = json_decode((string)$row['payload_json'], true);
        $row['payload'] = is_array($decoded) ? $decoded : [];
        return $row;
    }

    public function createPreviewToken($blogId, $requiredRole = null, $createdBy = null, $ttlHours = 24)
    {
        if (!$this->tableExists('blog_preview_tokens')) {
            return false;
        }

        $blogId = (int)$blogId;
        if ($blogId <= 0) {
            return false;
        }

        $role = trim((string)$requiredRole);
        if ($role === '' || $role === 'all') {
            $role = null;
        }

        $ttlHours = max(1, min((int)$ttlHours, 168));
        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlHours * 3600));

        try {
            $stmt = $this->link->prepare(
                "INSERT INTO blog_preview_tokens (blog_id, token, required_role, created_by, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );

            $ok = $stmt->execute([$blogId, $token, $role, $createdBy ? (int)$createdBy : null, $expiresAt]);
        } catch (Throwable $e) {
            return false;
        }

        if (!$ok) {
            return false;
        }

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'preview_path' => '/blog/preview/' . $token,
        ];
    }

    public function findPreviewByToken($token)
    {
        if (!$this->tableExists('blog_preview_tokens')) {
            return null;
        }

        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT pt.*, b.*
                 FROM blog_preview_tokens pt
                 JOIN {$this->table} b ON b.id = pt.blog_id
                 WHERE pt.token = ? AND pt.expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function deletePreviewToken($token)
    {
        if (!$this->tableExists('blog_preview_tokens')) {
            return false;
        }

        try {
            $stmt = $this->link->prepare("DELETE FROM blog_preview_tokens WHERE token = ?");
            return $stmt->execute([(string)$token]);
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

        if (!$this->tableExists($tableName)) {
            $this->columnExistsCache[$cacheKey] = false;
            return false;
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = ?
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

    private function normalizeTags($tags)
    {
        if (is_array($tags)) {
            $tags = implode(',', $tags);
        }
        $parts = array_filter(array_map('trim', explode(',', (string)$tags)));
        $parts = array_map(function ($tag) {
            return strtolower(preg_replace('/\s+/', '-', $tag));
        }, $parts);
        $parts = array_values(array_unique(array_filter($parts)));
        return implode(',', $parts);
    }
}
