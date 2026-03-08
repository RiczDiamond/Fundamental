<?php
// --------------------------------------------------
// Helper functions voor WordPress-like CMS
// --------------------------------------------------

/**
 * Haal een post op via slug en type
 * 
 * @param PDO $pdo
 * @param string $slug
 * @param string $type
 * @return array|null
 */
function get_post_by_slug(PDO $pdo, string $slug, string $type = 'post'): ?array {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM posts 
        WHERE post_name = :slug 
          AND post_type = :type
          AND post_status = 'publish'
        LIMIT 1
    ");
    $stmt->execute(['slug' => $slug, 'type' => $type]);
    return $stmt->fetch() ?: null;
}

/**
 * Haal een post op via een oude slug (slug history in postmeta)
 *
 * @param PDO $pdo
 * @param string $oldSlug
 * @param string $type
 * @return array|null
 */
function get_post_by_old_slug(PDO $pdo, string $oldSlug, string $type = 'post'): ?array {
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM posts p
        INNER JOIN postmeta pm ON pm.post_id = p.ID
        WHERE pm.meta_key = '_old_slug'
          AND pm.meta_value = :slug
          AND p.post_type = :type
          AND p.post_status = 'publish'
        ORDER BY p.post_modified DESC
        LIMIT 1
    ");
    $stmt->execute(['slug' => $oldSlug, 'type' => $type]);
    return $stmt->fetch() ?: null;
}

/**
 * Resolve een post op slug met fallback naar oude slug.
 *
 * @param PDO $pdo
 * @param string $slug
 * @param string $type
 * @return array|null
 */
function resolve_post_by_slug(PDO $pdo, string $slug, string $type = 'post'): ?array {
    $current = get_post_by_slug($pdo, $slug, $type);

    if ($current) {
        return [
            'post' => $current,
            'matched_old_slug' => false,
        ];
    }

    $old = get_post_by_old_slug($pdo, $slug, $type);

    if (!$old) {
        return null;
    }

    return [
        'post' => $old,
        'matched_old_slug' => true,
    ];
}

/**
 * Bewaar oude slug in postmeta als deze nog niet bestaat.
 *
 * @param PDO $pdo
 * @param int $postId
 * @param string $oldSlug
 * @return void
 */
function store_old_slug(PDO $pdo, int $postId, string $oldSlug): void {
    $oldSlug = trim($oldSlug);

    if ($oldSlug === '') {
        return;
    }

    $check = $pdo->prepare("
        SELECT 1
        FROM postmeta
        WHERE post_id = :post_id
          AND meta_key = '_old_slug'
          AND meta_value = :slug
        LIMIT 1
    ");
    $check->execute(['post_id' => $postId, 'slug' => $oldSlug]);

    if ($check->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO postmeta (post_id, meta_key, meta_value)
        VALUES (:post_id, '_old_slug', :slug)
    ");
    $insert->execute(['post_id' => $postId, 'slug' => $oldSlug]);
}

/**
 * Update slug van een pagina en bewaar de vorige slug voor redirects.
 *
 * @param PDO $pdo
 * @param int $postId
 * @param string $newSlug
 * @return bool
 */
function update_page_slug(PDO $pdo, int $postId, string $newSlug): bool {
    $newSlug = trim(strtolower($newSlug));

    if ($newSlug === '') {
        return false;
    }

    $currentStmt = $pdo->prepare("
        SELECT post_name
        FROM posts
        WHERE ID = :id
          AND post_type = 'page'
        LIMIT 1
    ");
    $currentStmt->execute(['id' => $postId]);
    $currentSlug = $currentStmt->fetchColumn();

    if ($currentSlug === false) {
        return false;
    }

    if ($currentSlug === $newSlug) {
        return true;
    }

    $pdo->beginTransaction();

    try {
        store_old_slug($pdo, $postId, $currentSlug);

        $update = $pdo->prepare("
            UPDATE posts
            SET post_name = :new_slug,
                post_modified = NOW()
            WHERE ID = :id
              AND post_type = 'page'
            LIMIT 1
        ");
        $update->execute(['new_slug' => $newSlug, 'id' => $postId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Haal alle posts van een bepaald type
 * 
 * @param PDO $pdo
 * @param string $type
 * @param int $limit
 * @return array
 */
function get_posts(PDO $pdo, string $type = 'post', int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM posts 
        WHERE post_type = :type 
          AND post_status = 'publish' 
        ORDER BY post_date DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Haal alle attachments van een parent post
 * 
 * @param PDO $pdo
 * @param int $parentId
 * @return array
 */
function get_attachments(PDO $pdo, int $parentId): array {
    $stmt = $pdo->prepare("
        SELECT p.*, pm.meta_value AS file
        FROM posts p
        LEFT JOIN postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
        WHERE p.post_type = 'attachment' 
          AND p.post_parent = :parent
        ORDER BY p.post_date DESC
    ");
    $stmt->execute(['parent' => $parentId]);
    return $stmt->fetchAll();
}

/**
 * Haal postmeta op voor een post
 * 
 * @param PDO $pdo
 * @param int $postId
 * @param string|null $key
 * @return array|mixed
 */
function get_post_meta(PDO $pdo, int $postId, ?string $key = null) {
    if ($key) {
        $stmt = $pdo->prepare("
            SELECT meta_value 
            FROM postmeta 
            WHERE post_id = :post_id AND meta_key = :key
            LIMIT 1
        ");
        $stmt->execute(['post_id' => $postId, 'key' => $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    } else {
        $stmt = $pdo->prepare("
            SELECT meta_key, meta_value 
            FROM postmeta 
            WHERE post_id = :post_id
        ");
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }
}

/**
 * ACF-achtige helper: haal een field op uit postmeta.
 *
 * @param PDO $pdo
 * @param int $postId
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_field(PDO $pdo, int $postId, string $key, mixed $default = null): mixed {
    $value = get_post_meta($pdo, $postId, $key);

    if ($value === null) {
        return $default;
    }

    return $value;
}

/**
 * Bouw vaste sections (hero/text/cta) op uit losse postmeta fields.
 *
 * @param PDO $pdo
 * @param int $postId
 * @return array
 */
function get_fixed_page_sections(PDO $pdo, int $postId): array {
    $sections = [];

    $heroTitle = (string) get_field($pdo, $postId, 'hero_title', '');
    $heroSubtitle = (string) get_field($pdo, $postId, 'hero_subtitle', '');
    $heroImage = (string) get_field($pdo, $postId, 'hero_image', '');

    if ($heroTitle !== '' || $heroSubtitle !== '' || $heroImage !== '') {
        $sections[] = [
            'type' => 'hero',
            'fields' => [
                'headline' => $heroTitle,
                'subline' => $heroSubtitle,
                'image' => $heroImage,
            ],
        ];
    }

    $introTitle = (string) get_field($pdo, $postId, 'intro_title', '');
    $introContent = (string) get_field($pdo, $postId, 'intro_content', '');

    if ($introTitle !== '' || $introContent !== '') {
        $sections[] = [
            'type' => 'text',
            'fields' => [
                'title' => $introTitle,
                'content' => $introContent,
            ],
        ];
    }

    $ctaTitle = (string) get_field($pdo, $postId, 'cta_title', '');
    $ctaLabel = (string) get_field($pdo, $postId, 'cta_button_label', '');
    $ctaUrl = (string) get_field($pdo, $postId, 'cta_button_url', '');

    if ($ctaTitle !== '' || $ctaLabel !== '' || $ctaUrl !== '') {
        $sections[] = [
            'type' => 'cta',
            'fields' => [
                'title' => $ctaTitle,
                'button_label' => $ctaLabel,
                'button_url' => $ctaUrl,
            ],
        ];
    }

    return $sections;
}

/**
 * Normaliseer section data uit JSON naar een vaste structuur.
 *
 * @param array $sections
 * @return array
 */
function normalize_flexible_sections(array $sections): array {
    $normalized = [];

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = $section['type']
            ?? $section['layout']
            ?? $section['acf_fc_layout']
            ?? null;

        if (!is_string($type) || trim($type) === '') {
            continue;
        }

        $fields = $section['fields'] ?? $section;

        if (!is_array($fields)) {
            $fields = [];
        }

        unset($fields['type'], $fields['layout'], $fields['acf_fc_layout'], $fields['fields']);

        $normalized[] = [
            'type' => strtolower(trim($type)),
            'fields' => $fields,
        ];
    }

    return $normalized;
}

/**
 * Haal ACF-achtige flexibele sections op uit postmeta JSON.
 *
 * @param PDO $pdo
 * @param int $postId
 * @param string $metaKey
 * @return array
 */
function get_flexible_sections(PDO $pdo, int $postId, string $metaKey = '_sections_json'): array {
    $raw = get_post_meta($pdo, $postId, $metaKey);

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return [];
    }

    return normalize_flexible_sections($decoded);
}

/**
 * Render flexibele sections via type-specifieke template files.
 *
 * @param array $sections
 * @param string|null $sectionsBasePath
 * @return void
 */
function render_flexible_sections(array $sections, ?string $sectionsBasePath = null): void {
    if ($sections === []) {
        return;
    }

    $basePath = $sectionsBasePath ?: __DIR__ . '/../_website/sections';

    foreach ($sections as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $type = (string) ($entry['type'] ?? '');
        $fields = $entry['fields'] ?? [];

        if (!is_array($fields)) {
            $fields = [];
        }

        $safeType = preg_replace('/[^a-z0-9\-_]/i', '', strtolower($type));

        if ($safeType === '') {
            continue;
        }

        $section = [
            'index' => (int) $index,
            'type' => $safeType,
            'fields' => $fields,
        ];

        $templatePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $safeType . '.php';

        if (is_file($templatePath)) {
            require $templatePath;
            continue;
        }

        echo '<!-- Unknown section type: ' . htmlspecialchars($safeType, ENT_QUOTES, 'UTF-8') . ' -->';
    }
}

/**
 * Haal user op via ID
 * 
 * @param PDO $pdo
 * @param int $userId
 * @return array|null
 */
function get_user_by_id(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE id = :id 
        LIMIT 1
    ");
    $stmt->execute(['id' => $userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Haal categorieën / tags van een post
 * 
 * @param PDO $pdo
 * @param int $postId
 * @return array
 */
function get_post_terms(PDO $pdo, int $postId): array {
    $stmt = $pdo->prepare("
        SELECT t.*, tt.taxonomy 
        FROM terms t
        INNER JOIN term_taxonomy tt ON tt.term_id = t.term_id
        INNER JOIN term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tr.object_id = :post_id
    ");
    $stmt->execute(['post_id' => $postId]);
    return $stmt->fetchAll();
}
?>