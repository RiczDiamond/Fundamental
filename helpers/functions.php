<?php
// --------------------------------------------------
// Helper functions voor WordPress-like CMS
// --------------------------------------------------

/**
 * Normaliseer een slug/path voor nette permalink-opbouw.
 */
function normalize_permalink_slug(string $slug): string {
    $slug = trim($slug);

    if ($slug === '') {
        return 'home';
    }

    $slug = trim($slug, "/ ");

    if ($slug === '') {
        return 'home';
    }

    $parts = preg_split('#/+#', $slug) ?: [];
    $safeParts = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);

        if ($part === '') {
            continue;
        }

        $part = preg_replace('/[^a-z0-9\-]/i', '', strtolower($part)) ?? '';

        if ($part !== '') {
            $safeParts[] = $part;
        }
    }

    if ($safeParts === []) {
        return 'home';
    }

    return implode('/', $safeParts);
}

/**
 * Bouw absolute permalink voor slug (home => root).
 */
function get_permalink_by_slug(string $slug): string {
    $normalized = normalize_permalink_slug($slug);

    if ($normalized === 'home') {
        return BASE_URL . '/';
    }

    $parts = explode('/', $normalized);
    $encodedParts = array_map(static fn (string $part): string => rawurlencode($part), $parts);

    return BASE_URL . '/' . implode('/', $encodedParts) . '/';
}

/**
 * Bouw absolute permalink voor post-array.
 *
 * @param array<string, mixed> $post
 */
function get_post_permalink(array $post): string {
    $slug = (string) ($post['post_name'] ?? 'home');
    return get_permalink_by_slug($slug);
}

if (!function_exists('home_url')) {
    /**
     * WordPress-achtige helper voor absolute site-URL.
     */
    function home_url(string $path = ''): string {
        $base = rtrim(BASE_URL, '/');
        $path = trim($path);

        if ($path === '') {
            return $base . '/';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('get_query_var')) {
    /**
     * WordPress-achtige query var reader met fallback naar $_GET.
     */
    function get_query_var(string $key, mixed $default = ''): mixed {
        global $params;

        if (isset($params) && is_array($params) && array_key_exists($key, $params)) {
            return $params[$key];
        }

        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $default;
    }
}

if (!function_exists('wp_unslash')) {
    /**
     * Verwijder slashes op strings of recursief op arrays.
     */
    function wp_unslash(mixed $value): mixed {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = wp_unslash($item);
            }
            return $result;
        }

        if (is_string($value)) {
            return stripslashes($value);
        }

        return $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    /**
     * WordPress-achtige simpele text sanitization.
     */
    function sanitize_text_field(mixed $value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $text = (string) $value;
        $text = wp_unslash($text);
        $text = strip_tags($text);
        $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? '';
        return trim($text);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    /**
     * WordPress-achtige sanitization voor textarea-content.
     */
    function sanitize_textarea_field(mixed $value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $text = (string) $value;
        $text = wp_unslash($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\x00+/', '', $text) ?? '';
        return trim($text);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(mixed $email): string {
        if (!is_scalar($email)) {
            return '';
        }

        $clean = filter_var((string) wp_unslash($email), FILTER_SANITIZE_EMAIL);
        return is_string($clean) ? trim($clean) : '';
    }
}

if (!function_exists('is_email')) {
    function is_email(mixed $email): bool {
        $clean = sanitize_email($email);
        return $clean !== '' && filter_var($clean, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('absint')) {
    function absint(mixed $value): int {
        return abs((int) $value);
    }
}

if (!function_exists('sanitize_title')) {
    /**
     * WordPress-achtige slug normalizer.
     */
    function sanitize_title(mixed $title, string $fallback = ''): string {
        $raw = sanitize_text_field($title);
        $raw = strtolower($raw);
        $raw = preg_replace('/[^a-z0-9]+/', '-', $raw) ?? '';
        $raw = trim($raw, '-');

        if ($raw !== '') {
            return $raw;
        }

        $fallback = sanitize_text_field($fallback);
        if ($fallback === '') {
            return '';
        }

        $fallback = strtolower($fallback);
        $fallback = preg_replace('/[^a-z0-9]+/', '-', $fallback) ?? '';
        return trim($fallback, '-');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = '-1'): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['_wp_nonce']) || !is_array($_SESSION['_wp_nonce'])) {
            $_SESSION['_wp_nonce'] = [];
        }

        $bucket = $_SESSION['_wp_nonce'];
        $entry = $bucket[$action] ?? null;
        $isValidEntry = is_array($entry) && isset($entry['token'], $entry['created_at']) && is_string($entry['token']);

        if ($isValidEntry) {
            $createdAt = (int) ($entry['created_at'] ?? 0);
            if ($createdAt > 0 && (time() - $createdAt) <= 7200) {
                return $entry['token'];
            }
        }

        $token = bin2hex(random_bytes(16));
        $_SESSION['_wp_nonce'][$action] = [
            'token' => $token,
            'created_at' => time(),
        ];

        return $token;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(mixed $nonce, string $action = '-1'): bool {
        if (!is_string($nonce) || $nonce === '') {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $entry = $_SESSION['_wp_nonce'][$action] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $token = (string) ($entry['token'] ?? '');
        $createdAt = (int) ($entry['created_at'] ?? 0);

        if ($token === '' || $createdAt <= 0) {
            return false;
        }

        if ((time() - $createdAt) > 7200) {
            unset($_SESSION['_wp_nonce'][$action]);
            return false;
        }

        return hash_equals($token, $nonce);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action = '-1', string $name = '_wpnonce'): void {
        $token = wp_create_nonce($action);
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($token) . '">';
    }
}

if (!function_exists('wp_require_valid_nonce')) {
    /**
     * Vereenvoudigde nonce check voor POST/GET bronnen.
     *
     * @param string $action
     * @param string $name
     * @param array<string,mixed>|null $source
     */
    function wp_require_valid_nonce(string $action, string $name = '_wpnonce', ?array $source = null): bool {
        $source = is_array($source) ? $source : $_POST;
        return wp_verify_nonce($source[$name] ?? '', $action);
    }
}

if (!function_exists('esc_html')) {
    function esc_html(mixed $text): string {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(mixed $text): string {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea(mixed $text): string {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if ($url[0] === '/' || $url[0] === '?' || $url[0] === '#') {
            return esc_attr($url);
        }

        $safe = filter_var($url, FILTER_SANITIZE_URL);

        if (!is_string($safe) || $safe === '') {
            return '';
        }

        if (filter_var($safe, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return esc_attr($safe);
    }
}

if (!function_exists('wp_redirect')) {
    /**
     * Redirect helper.
     */
    function wp_redirect(string $location, int $status = 302): void {
        header('Location: ' . $location, true, $status);
        exit;
    }
}

if (!function_exists('wp_safe_redirect')) {
    /**
     * Redirect alleen naar huidige host of relatieve URL.
     */
    function wp_safe_redirect(string $location, int $status = 302): void {
        $location = trim($location);

        if ($location === '') {
            wp_redirect(home_url('/'), $status);
        }

        if ($location[0] === '/' || $location[0] === '?' || $location[0] === '#') {
            wp_redirect(home_url($location), $status);
        }

        $targetHost = strtolower((string) parse_url($location, PHP_URL_HOST));
        $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $currentHost = preg_replace('/:\d+$/', '', $currentHost) ?? '';

        if ($targetHost !== '' && $targetHost === $currentHost) {
            wp_redirect($location, $status);
        }

        wp_redirect(home_url('/'), $status);
    }
}

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
 * Centrale definitie van section types.
 *
 * @return array<string, array<string, mixed>>
 */
function get_section_schemas(): array {
    static $schemas = null;

    if (is_array($schemas)) {
        return $schemas;
    }

    $schemas = [
        'hero' => [
            'hint' => 'Gebruikt: headline, subline',
            'editor_fields' => ['headline', 'subline'],
            'data_fields' => ['headline', 'subline'],
        ],
        'text' => [
            'hint' => 'Gebruikt: title, content',
            'editor_fields' => ['title', 'content'],
            'data_fields' => ['title', 'content'],
        ],
        'cta' => [
            'hint' => 'Gebruikt: title, button_label, button_url',
            'editor_fields' => ['title', 'button_label', 'button_url'],
            'data_fields' => ['title', 'button_label', 'button_url'],
        ],
        'features' => [
            'hint' => 'Gebruikt: title, intro(content), items (regels)',
            'editor_fields' => ['title', 'content', 'items_lines'],
            'data_fields' => ['title', 'intro', 'items'],
        ],
        'faq' => [
            'hint' => 'Gebruikt: title, items (Vraag|Antwoord per regel)',
            'editor_fields' => ['title', 'items_lines'],
            'data_fields' => ['title', 'items'],
        ],
        'media-text' => [
            'hint' => 'Gebruikt: title, content, image, button_label, button_url',
            'editor_fields' => ['title', 'content', 'image', 'button_label', 'button_url'],
            'data_fields' => ['title', 'content', 'image', 'button_label', 'button_url'],
        ],
        'stats' => [
            'hint' => 'Gebruikt: title, items (Waarde|Label per regel)',
            'editor_fields' => ['title', 'items_lines'],
            'data_fields' => ['title', 'items'],
        ],
        'testimonial' => [
            'hint' => 'Gebruikt: quote, author, role',
            'editor_fields' => ['quote', 'author', 'role'],
            'data_fields' => ['quote', 'author', 'role'],
        ],
    ];

    return $schemas;
}

/**
 * @return string[]
 */
function get_section_types(): array {
    return array_keys(get_section_schemas());
}

function is_valid_section_type(string $type): bool {
    return isset(get_section_schemas()[$type]);
}

/**
 * @return string[]
 */
function get_section_editor_fields(string $type): array {
    $schema = get_section_schemas()[$type] ?? null;

    if (!is_array($schema) || !isset($schema['editor_fields']) || !is_array($schema['editor_fields'])) {
        return ['title', 'content'];
    }

    return array_values(array_map('strval', $schema['editor_fields']));
}

/**
 * @return array<string, array<int, string>>
 */
function get_section_editor_fields_map(): array {
    $map = [];

    foreach (get_section_types() as $type) {
        $map[$type] = get_section_editor_fields($type);
    }

    return $map;
}

function section_type_hint(string $type): string {
    $schema = get_section_schemas()[$type] ?? null;

    if (is_array($schema) && isset($schema['hint']) && is_string($schema['hint'])) {
        return $schema['hint'];
    }

    return 'Vul de velden in die je nodig hebt voor dit type.';
}

/**
 * Lees een stringveld veilig uit een array.
 */
function section_read_string(array $source, string $key): string {
    if (!isset($source[$key]) || !is_scalar($source[$key])) {
        return '';
    }

    return trim((string) $source[$key]);
}

/**
 * Normaliseer items-collecties voor features/faq/stats.
 *
 * @param mixed $rawItems
 * @return array
 */
function section_normalize_items(string $type, $rawItems): array {
    if (!is_array($rawItems)) {
        return [];
    }

    $items = [];

    foreach ($rawItems as $item) {
        if ($type === 'features') {
            $label = trim((string) $item);
            if ($label !== '') {
                $items[] = $label;
            }
            continue;
        }

        if (!is_array($item)) {
            continue;
        }

        if ($type === 'faq') {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            $items[] = [
                'question' => $question,
                'answer' => $answer,
            ];
            continue;
        }

        if ($type === 'stats') {
            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($value === '' && $label === '') {
                continue;
            }

            $items[] = [
                'value' => $value,
                'label' => $label,
            ];
        }
    }

    return $items;
}

/**
 * Zet section-items om naar editorregels.
 */
function section_items_to_lines(string $type, array $items): string {
    $lines = [];

    foreach ($items as $item) {
        if ($type === 'features') {
            $label = trim((string) $item);
            if ($label !== '') {
                $lines[] = $label;
            }
            continue;
        }

        if (!is_array($item)) {
            continue;
        }

        if ($type === 'faq') {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question !== '' || $answer !== '') {
                $lines[] = $question . '|' . $answer;
            }
            continue;
        }

        if ($type === 'stats') {
            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($value !== '' || $label !== '') {
                $lines[] = $value . '|' . $label;
            }
        }
    }

    return implode("\n", $lines);
}

/**
 * Parse editorregels naar section-items.
 */
function section_lines_to_items(string $type, string $linesRaw): array {
    $lines = preg_split('/\r\n|\r|\n/', $linesRaw) ?: [];
    $items = [];

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '') {
            continue;
        }

        if ($type === 'features') {
            $items[] = $line;
            continue;
        }

        $parts = explode('|', $line, 2);
        $left = trim((string) ($parts[0] ?? ''));
        $right = trim((string) ($parts[1] ?? ''));

        if ($type === 'faq') {
            $items[] = ['question' => $left, 'answer' => $right];
            continue;
        }

        if ($type === 'stats') {
            $items[] = ['value' => $left, 'label' => $right];
        }
    }

    return $items;
}

/**
 * Filter en normaliseer section fields conform schema.
 *
 * @param string $type
 * @param array $fields
 * @return array
 */
function normalize_section_fields(string $type, array $fields): array {
    $type = strtolower(trim($type));

    if (!is_valid_section_type($type)) {
        return [];
    }

    $safe = [];

    if ($type === 'hero') {
        $safe['headline'] = section_read_string($fields, 'headline');
        $safe['subline'] = section_read_string($fields, 'subline');
        return $safe;
    }

    if ($type === 'text') {
        $safe['title'] = section_read_string($fields, 'title');
        $safe['content'] = section_read_string($fields, 'content');
        return $safe;
    }

    if ($type === 'cta') {
        $safe['title'] = section_read_string($fields, 'title');
        $safe['button_label'] = section_read_string($fields, 'button_label');
        $safe['button_url'] = section_read_string($fields, 'button_url');
        return $safe;
    }

    if ($type === 'media-text') {
        $safe['title'] = section_read_string($fields, 'title');
        $safe['content'] = section_read_string($fields, 'content');
        $safe['image'] = section_read_string($fields, 'image');
        $safe['button_label'] = section_read_string($fields, 'button_label');
        $safe['button_url'] = section_read_string($fields, 'button_url');
        return $safe;
    }

    if ($type === 'testimonial') {
        $safe['quote'] = section_read_string($fields, 'quote');
        $safe['author'] = section_read_string($fields, 'author');
        $safe['role'] = section_read_string($fields, 'role');
        return $safe;
    }

    if ($type === 'features') {
        $safe['title'] = section_read_string($fields, 'title');
        $safe['intro'] = section_read_string($fields, 'intro');
        $safe['items'] = section_normalize_items('features', $fields['items'] ?? []);
        return $safe;
    }

    if ($type === 'faq' || $type === 'stats') {
        $safe['title'] = section_read_string($fields, 'title');
        $safe['items'] = section_normalize_items($type, $fields['items'] ?? []);
        return $safe;
    }

    return [];
}

/**
 * Bouw editor values op vanuit opgeslagen section fields.
 *
 * @param string $type
 * @param array $fields
 * @return array<string, string>
 */
function section_fields_to_form(string $type, array $fields): array {
    $type = strtolower(trim($type));
    $fields = normalize_section_fields($type, $fields);

    $form = [
        'headline' => '',
        'subline' => '',
        'title' => '',
        'content' => '',
        'image' => '',
        'button_label' => '',
        'button_url' => '',
        'quote' => '',
        'author' => '',
        'role' => '',
        'items_lines' => '',
    ];

    foreach ($form as $key => $defaultValue) {
        if (isset($fields[$key]) && is_scalar($fields[$key])) {
            $form[$key] = (string) $fields[$key];
        }
    }

    if (($type === 'features' || $type === 'faq' || $type === 'stats') && isset($fields['items']) && is_array($fields['items'])) {
        $form['items_lines'] = section_items_to_lines($type, $fields['items']);
    }

    if ($type === 'features') {
        $form['content'] = (string) ($fields['intro'] ?? '');
    }

    return $form;
}

/**
 * Bouw gestandaardiseerde section fields op uit editor-input.
 *
 * @param string $type
 * @param array $input
 * @return array
 */
function section_fields_from_form(string $type, array $input): array {
    $type = strtolower(trim($type));

    $fields = [];

    if ($type === 'hero') {
        $fields['headline'] = section_read_string($input, 'headline');
        $fields['subline'] = section_read_string($input, 'subline');
        return normalize_section_fields($type, $fields);
    }

    if ($type === 'text') {
        $fields['title'] = section_read_string($input, 'title');
        $fields['content'] = section_read_string($input, 'content');
        return normalize_section_fields($type, $fields);
    }

    if ($type === 'cta') {
        $fields['title'] = section_read_string($input, 'title');
        $fields['button_label'] = section_read_string($input, 'button_label');
        $fields['button_url'] = section_read_string($input, 'button_url');
        return normalize_section_fields($type, $fields);
    }

    if ($type === 'media-text') {
        $fields['title'] = section_read_string($input, 'title');
        $fields['content'] = section_read_string($input, 'content');
        $fields['image'] = section_read_string($input, 'image');
        $fields['button_label'] = section_read_string($input, 'button_label');
        $fields['button_url'] = section_read_string($input, 'button_url');
        return normalize_section_fields($type, $fields);
    }

    if ($type === 'testimonial') {
        $fields['quote'] = section_read_string($input, 'quote');
        $fields['author'] = section_read_string($input, 'author');
        $fields['role'] = section_read_string($input, 'role');
        return normalize_section_fields($type, $fields);
    }

    if ($type === 'features' || $type === 'faq' || $type === 'stats') {
        $fields['title'] = section_read_string($input, 'title');

        if ($type === 'features') {
            $fields['intro'] = section_read_string($input, 'content');
        }

        $fields['items'] = section_lines_to_items($type, section_read_string($input, 'items_lines'));
        return normalize_section_fields($type, $fields);
    }

    $fields['title'] = section_read_string($input, 'title');
    $fields['content'] = section_read_string($input, 'content');
    return normalize_section_fields('text', $fields);
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

        $safeType = preg_replace('/[^a-z0-9\-_]/i', '', strtolower(trim($type))) ?? '';

        if ($safeType === '' || !is_valid_section_type($safeType)) {
            continue;
        }

        $normalizedFields = normalize_section_fields($safeType, $fields);

        $normalized[] = [
            'type' => $safeType,
            'fields' => $normalizedFields,
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
 * Haal page sections op met fallback van flexible naar fixed.
 */
function get_page_sections(PDO $pdo, int $postId): array {
    $sections = get_flexible_sections($pdo, $postId);

    if ($sections !== []) {
        return $sections;
    }

    return get_fixed_page_sections($pdo, $postId);
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

    $basePath = $sectionsBasePath ?: __DIR__ . '/../_website/blocks';

    // Backward compatibility for older setups that still use _website/sections.
    if (!is_dir($basePath) && $sectionsBasePath === null) {
        $legacyPath = __DIR__ . '/../_website/sections';
        if (is_dir($legacyPath)) {
            $basePath = $legacyPath;
        }
    }

    $componentHelpers = __DIR__ . '/../_website/partials/components.php';
    if (is_file($componentHelpers)) {
        require_once $componentHelpers;
    }

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

        if ($safeType === '' || !is_valid_section_type($safeType)) {
            continue;
        }

        $fields = normalize_section_fields($safeType, $fields);

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

        echo '<!-- Unknown section type: ' . esc_html($safeType) . ' -->';
    }
}

/**
 * Haal option_value op uit options tabel.
 */
function get_option_value(PDO $pdo, string $name, ?string $default = null): ?string {
    $stmt = $pdo->prepare("\n        SELECT option_value\n        FROM options\n        WHERE option_name = :name\n        LIMIT 1\n    ");
    $stmt->execute(['name' => $name]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        return $default;
    }

    return (string) $value;
}

/**
 * Insert/update option in options tabel.
 */
function upsert_option_value(PDO $pdo, string $name, string $value, string $autoload = 'yes'): void {
    $existing = $pdo->prepare("\n        SELECT option_id\n        FROM options\n        WHERE option_name = :name\n        LIMIT 1\n    ");
    $existing->execute(['name' => $name]);
    $optionId = $existing->fetchColumn();

    if ($optionId !== false) {
        $update = $pdo->prepare("\n            UPDATE options\n            SET option_value = :value, autoload = :autoload\n            WHERE option_id = :id\n            LIMIT 1\n        ");
        $update->execute([
            'value' => $value,
            'autoload' => $autoload,
            'id' => (int) $optionId,
        ]);
        return;
    }

    $insert = $pdo->prepare("\n        INSERT INTO options (option_name, option_value, autoload)\n        VALUES (:name, :value, :autoload)\n    ");
    $insert->execute([
        'name' => $name,
        'value' => $value,
        'autoload' => $autoload,
    ]);
}

/**
 * Maak revisions tabel aan indien nodig.
 */
function ensure_post_revisions_table(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS post_revisions (\n            id bigint UNSIGNED NOT NULL AUTO_INCREMENT,\n            post_id bigint UNSIGNED NOT NULL,\n            user_id bigint UNSIGNED NOT NULL DEFAULT 0,\n            action varchar(20) NOT NULL DEFAULT 'update',\n            post_title text NOT NULL,\n            post_name varchar(200) NOT NULL DEFAULT '',\n            post_content longtext NOT NULL,\n            post_status varchar(20) NOT NULL DEFAULT 'publish',\n            sections_json longtext NULL,\n            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            KEY post_id_created (post_id, created_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n    ");
}

/**
 * Sla snapshot van huidige post op als revision.
 *
 * @param array<string,mixed> $post
 */
function create_post_revision(PDO $pdo, array $post, int $userId, string $action = 'update'): void {
    ensure_post_revisions_table($pdo);

    $postId = (int) ($post['ID'] ?? 0);
    if ($postId < 1) {
        return;
    }

    $sectionsJson = get_post_meta($pdo, $postId, '_sections_json');
    if (!is_string($sectionsJson)) {
        $sectionsJson = '';
    }

    $insert = $pdo->prepare("\n        INSERT INTO post_revisions (\n            post_id, user_id, action, post_title, post_name, post_content, post_status, sections_json\n        ) VALUES (\n            :post_id, :user_id, :action, :post_title, :post_name, :post_content, :post_status, :sections_json\n        )\n    ");
    $insert->execute([
        'post_id' => $postId,
        'user_id' => $userId,
        'action' => substr($action, 0, 20),
        'post_title' => (string) ($post['post_title'] ?? ''),
        'post_name' => (string) ($post['post_name'] ?? ''),
        'post_content' => (string) ($post['post_content'] ?? ''),
        'post_status' => (string) ($post['post_status'] ?? 'publish'),
        'sections_json' => $sectionsJson,
    ]);
}

/**
 * @return array<int,array<string,mixed>>
 */
function get_post_revisions(PDO $pdo, int $postId, int $limit = 25): array {
    ensure_post_revisions_table($pdo);

    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("\n        SELECT *\n        FROM post_revisions\n        WHERE post_id = :post_id\n        ORDER BY id DESC\n        LIMIT :limit\n    ");
    $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return array<string,mixed>|null
 */
function get_post_revision_by_id(PDO $pdo, int $revisionId, int $postId): ?array {
    ensure_post_revisions_table($pdo);

    $stmt = $pdo->prepare("\n        SELECT *\n        FROM post_revisions\n        WHERE id = :id AND post_id = :post_id\n        LIMIT 1\n    ");
    $stmt->execute([
        'id' => $revisionId,
        'post_id' => $postId,
    ]);

    $revision = $stmt->fetch();
    return $revision ?: null;
}

/**
 * Herstel post + sections vanuit revision.
 *
 * @param array<string,mixed> $revision
 */
function restore_post_from_revision(PDO $pdo, array $revision, int $userId): bool {
    $postId = (int) ($revision['post_id'] ?? 0);
    if ($postId < 1) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $currentStmt = $pdo->prepare("\n            SELECT ID, post_title, post_name, post_content, post_status\n            FROM posts\n            WHERE ID = :id\n            LIMIT 1\n        ");
        $currentStmt->execute(['id' => $postId]);
        $currentPost = $currentStmt->fetch();

        if (!$currentPost) {
            $pdo->rollBack();
            return false;
        }

        create_post_revision($pdo, $currentPost, $userId, 'restore');

        $update = $pdo->prepare("\n            UPDATE posts\n            SET post_title = :title,\n                post_name = :slug,\n                post_content = :content,\n                post_status = :status,\n                post_modified = NOW()\n            WHERE ID = :id\n            LIMIT 1\n        ");
        $update->execute([
            'title' => (string) ($revision['post_title'] ?? ''),
            'slug' => (string) ($revision['post_name'] ?? ''),
            'content' => (string) ($revision['post_content'] ?? ''),
            'status' => (string) ($revision['post_status'] ?? 'publish'),
            'id' => $postId,
        ]);

        $sectionsJson = (string) ($revision['sections_json'] ?? '');
        upsert_post_meta($pdo, $postId, '_sections_json', $sectionsJson);

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
 * Maak contact submissions tabel aan indien nodig.
 */
function ensure_contact_submissions_table(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS contact_submissions (\n            id bigint UNSIGNED NOT NULL AUTO_INCREMENT,\n            name varchar(191) NOT NULL DEFAULT '',\n            email varchar(191) NOT NULL DEFAULT '',\n            subject varchar(255) NOT NULL DEFAULT '',\n            message longtext NOT NULL,\n            page_url varchar(255) NOT NULL DEFAULT '',\n            ip_address varchar(64) NOT NULL DEFAULT '',\n            user_agent varchar(255) NOT NULL DEFAULT '',\n            mail_sent tinyint(1) NOT NULL DEFAULT 0,\n            is_read tinyint(1) NOT NULL DEFAULT 0,\n            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            KEY created_at (created_at),\n            KEY is_read_created (is_read, created_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n    ");
}

/**
 * @param array<string,mixed> $data
 */
function create_contact_submission(PDO $pdo, array $data): int {
    ensure_contact_submissions_table($pdo);

    $insert = $pdo->prepare("\n        INSERT INTO contact_submissions (\n            name, email, subject, message, page_url, ip_address, user_agent, mail_sent\n        ) VALUES (\n            :name, :email, :subject, :message, :page_url, :ip_address, :user_agent, :mail_sent\n        )\n    ");

    $insert->execute([
        'name' => substr(trim((string) ($data['name'] ?? '')), 0, 191),
        'email' => substr(trim((string) ($data['email'] ?? '')), 0, 191),
        'subject' => substr(trim((string) ($data['subject'] ?? '')), 0, 255),
        'message' => trim((string) ($data['message'] ?? '')),
        'page_url' => substr(trim((string) ($data['page_url'] ?? '')), 0, 255),
        'ip_address' => substr(trim((string) ($data['ip_address'] ?? '')), 0, 64),
        'user_agent' => substr(trim((string) ($data['user_agent'] ?? '')), 0, 255),
        'mail_sent' => !empty($data['mail_sent']) ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

function update_contact_submission_mail_status(PDO $pdo, int $submissionId, bool $mailSent): void {
    ensure_contact_submissions_table($pdo);

    $update = $pdo->prepare("\n        UPDATE contact_submissions\n        SET mail_sent = :mail_sent\n        WHERE id = :id\n        LIMIT 1\n    ");
    $update->execute([
        'mail_sent' => $mailSent ? 1 : 0,
        'id' => $submissionId,
    ]);
}

/**
 * @return array<int,array<string,mixed>>
 */
function get_contact_submissions(PDO $pdo, int $limit = 200, string $query = ''): array {
    ensure_contact_submissions_table($pdo);

    $limit = max(1, min(500, $limit));
    $query = trim($query);

    $sql = "\n        SELECT *\n        FROM contact_submissions\n    ";
    $bind = [];

    if ($query !== '') {
        $sql .= " WHERE name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q ";
        $bind['q'] = '%' . $query . '%';
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT :limit ";

    $stmt = $pdo->prepare($sql);

    foreach ($bind as $k => $v) {
        $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return array<string,mixed>|null
 */
function get_contact_submission_by_id(PDO $pdo, int $id): ?array {
    ensure_contact_submissions_table($pdo);

    $stmt = $pdo->prepare("\n        SELECT *\n        FROM contact_submissions\n        WHERE id = :id\n        LIMIT 1\n    ");
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function mark_contact_submission_read(PDO $pdo, int $id): void {
    ensure_contact_submissions_table($pdo);

    $stmt = $pdo->prepare("\n        UPDATE contact_submissions\n        SET is_read = 1\n        WHERE id = :id\n        LIMIT 1\n    ");
    $stmt->execute(['id' => $id]);
}

/**
 * Probeer PHPMailer classes te laden via Composer autoload.
 */
function load_phpmailer(): bool {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }

    $autoloadPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

/**
 * Verstuur e-mail via PHPMailer SMTP config uit MAIL constant.
 */
function send_mail_phpmailer(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null, ?string &$errorMessage = null): bool {
    if (!load_phpmailer()) {
        $errorMessage = 'PHPMailer niet gevonden (vendor/autoload.php ontbreekt).';
        return false;
    }

    if (!defined('MAIL')) {
        $errorMessage = 'MAIL configuratie ontbreekt.';
        return false;
    }

    $from = (string) (MAIL['FROM'] ?? 'noreply@example.com');
    $fromName = (string) (MAIL['NAME'] ?? 'Website');
    $host = (string) (MAIL['HOST'] ?? '');
    $port = (int) (MAIL['PORT'] ?? 587);
    $user = (string) (MAIL['USER'] ?? '');
    $pass = (string) (MAIL['PASS'] ?? '');
    $secure = (string) (MAIL['SECURE'] ?? 'tls');
    $debugLevel = (int) (MAIL['DEBUG'] ?? 0);

    if ($host === '' || $user === '' || $pass === '') {
        $errorMessage = 'MAIL config is incompleet (HOST/USER/PASS).';
        return false;
    }

    if (stripos($pass, 'JE_EMAIL_WACHTWOORD') !== false) {
        $errorMessage = 'MAIL PASS staat nog op placeholder JE_EMAIL_WACHTWOORD.';
        return false;
    }

    try {
        $smtpLog = '';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->Port = $port;

        if ($secure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if ($debugLevel > 0) {
            $mail->SMTPDebug = $debugLevel;
            $mail->Debugoutput = static function (string $str, int $level) use (&$smtpLog): void {
                $smtpLog .= '[' . $level . '] ' . $str . "\n";
            };
        }

        $mail->CharSet = 'UTF-8';
        // For SMTP providers like Office365, sender should match authenticated mailbox.
        $effectiveFrom = $user !== '' ? $user : $from;
        $mail->setFrom($effectiveFrom, $fromName);
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);

        if ($replyTo !== null && trim($replyTo) !== '') {
            $mail->addReplyTo(trim($replyTo));
        }

        if ($from !== '' && strcasecmp($from, $effectiveFrom) !== 0) {
            $mail->addReplyTo($from, $fromName);
        }

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        if (isset($smtpLog) && $smtpLog !== '') {
            $errorMessage .= "\nSMTP debug:\n" . trim($smtpLog);
        }
        return false;
    }
}

/**
 * Maak tabel voor uitgaande contact-replies aan indien nodig.
 */
function ensure_contact_replies_table(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS contact_replies (\n            id bigint UNSIGNED NOT NULL AUTO_INCREMENT,\n            submission_id bigint UNSIGNED NOT NULL,\n            user_id bigint UNSIGNED NOT NULL DEFAULT 0,\n            to_email varchar(191) NOT NULL DEFAULT '',\n            subject varchar(255) NOT NULL DEFAULT '',\n            body longtext NOT NULL,\n            mail_sent tinyint(1) NOT NULL DEFAULT 0,\n            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            KEY submission_created (submission_id, created_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n    ");
}

/**
 * @param array<string,mixed> $data
 */
function create_contact_reply(PDO $pdo, array $data): int {
    ensure_contact_replies_table($pdo);

    $insert = $pdo->prepare("\n        INSERT INTO contact_replies (\n            submission_id, user_id, to_email, subject, body, mail_sent\n        ) VALUES (\n            :submission_id, :user_id, :to_email, :subject, :body, :mail_sent\n        )\n    ");

    $insert->execute([
        'submission_id' => (int) ($data['submission_id'] ?? 0),
        'user_id' => (int) ($data['user_id'] ?? 0),
        'to_email' => substr(trim((string) ($data['to_email'] ?? '')), 0, 191),
        'subject' => substr(trim((string) ($data['subject'] ?? '')), 0, 255),
        'body' => trim((string) ($data['body'] ?? '')),
        'mail_sent' => !empty($data['mail_sent']) ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return array<int,array<string,mixed>>
 */
function get_contact_replies(PDO $pdo, int $submissionId): array {
    ensure_contact_replies_table($pdo);

    $stmt = $pdo->prepare("\n        SELECT *\n        FROM contact_replies\n        WHERE submission_id = :submission_id\n        ORDER BY created_at DESC, id DESC\n    ");
    $stmt->execute(['submission_id' => $submissionId]);

    return $stmt->fetchAll();
}

/**
 * Maak tabel voor inkomende mailbox-berichten (via IMAP) aan indien nodig.
 */
function ensure_contact_incoming_table(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS contact_incoming (\n            id bigint UNSIGNED NOT NULL AUTO_INCREMENT,\n            submission_id bigint UNSIGNED NOT NULL DEFAULT 0,\n            from_name varchar(191) NOT NULL DEFAULT '',\n            from_email varchar(191) NOT NULL DEFAULT '',\n            subject varchar(255) NOT NULL DEFAULT '',\n            body longtext NOT NULL,\n            message_id varchar(255) DEFAULT NULL,\n            in_reply_to varchar(255) DEFAULT NULL,\n            references_header text,\n            mail_date varchar(191) NOT NULL DEFAULT '',\n            received_at datetime DEFAULT NULL,\n            source_hash char(64) NOT NULL DEFAULT '',\n            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_source_hash (source_hash),\n            KEY submission_created (submission_id, created_at),\n            KEY from_email_created (from_email, created_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n    ");
}

/**
 * @param array<string,mixed> $data
 */
function create_contact_incoming(PDO $pdo, array $data): bool {
    ensure_contact_incoming_table($pdo);

    $stmt = $pdo->prepare("\n        INSERT INTO contact_incoming (\n            submission_id, from_name, from_email, subject, body,\n            message_id, in_reply_to, references_header, mail_date, received_at, source_hash\n        ) VALUES (\n            :submission_id, :from_name, :from_email, :subject, :body,\n            :message_id, :in_reply_to, :references_header, :mail_date, :received_at, :source_hash\n        )\n    ");

    try {
        $stmt->execute([
            'submission_id' => (int) ($data['submission_id'] ?? 0),
            'from_name' => substr(trim((string) ($data['from_name'] ?? '')), 0, 191),
            'from_email' => substr(trim((string) ($data['from_email'] ?? '')), 0, 191),
            'subject' => substr(trim((string) ($data['subject'] ?? '')), 0, 255),
            'body' => trim((string) ($data['body'] ?? '')),
            'message_id' => ($data['message_id'] ?? '') !== '' ? substr((string) $data['message_id'], 0, 255) : null,
            'in_reply_to' => ($data['in_reply_to'] ?? '') !== '' ? substr((string) $data['in_reply_to'], 0, 255) : null,
            'references_header' => ($data['references_header'] ?? '') !== '' ? (string) $data['references_header'] : null,
            'mail_date' => substr(trim((string) ($data['mail_date'] ?? '')), 0, 191),
            'received_at' => ($data['received_at'] ?? null) ?: null,
            'source_hash' => (string) ($data['source_hash'] ?? ''),
        ]);

        return true;
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            // Duplicate source_hash means message already imported.
            return false;
        }
        throw $e;
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function get_contact_incoming_messages(PDO $pdo, int $submissionId, int $limit = 50): array {
    ensure_contact_incoming_table($pdo);

    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("\n        SELECT *\n        FROM contact_incoming\n        WHERE submission_id = :submission_id\n        ORDER BY received_at DESC, created_at DESC, id DESC\n        LIMIT :limit\n    ");
    $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function decode_mime_header_value(string $value): string {
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if (is_string($decoded) && $decoded !== '') {
            return trim($decoded);
        }
    }

    return trim($value);
}

/**
 * @return array{0:string,1:string}
 */
function parse_email_address(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
        return ['', ''];
    }

    if (preg_match('/^(.*)<([^>]+)>$/', $raw, $m) === 1) {
        $name = trim(str_replace('"', '', $m[1]));
        $email = trim($m[2]);
        return [decode_mime_header_value($name), strtolower($email)];
    }

    if (is_email($raw)) {
        return ['', strtolower(sanitize_email($raw))];
    }

    return [decode_mime_header_value($raw), ''];
}

function normalize_message_identifier(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return trim($value, " <>\t\r\n");
}

function extract_header_value(string $headers, string $name): string {
    if ($headers === '') {
        return '';
    }

    $pattern = '/^' . preg_quote($name, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $headers, $m) !== 1) {
        return '';
    }

    return trim(preg_replace('/\r?\n[ \t]+/', ' ', $m[1]) ?? '');
}

function parse_mail_date_to_datetime(?string $mailDate): ?string {
    $mailDate = trim((string) $mailDate);
    if ($mailDate === '') {
        return null;
    }

    $ts = strtotime($mailDate);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

function decode_imap_part_content(string $content, int $encoding): string {
    if ($content === '') {
        return '';
    }

    if ($encoding === 3) {
        $decoded = base64_decode($content, true);
        if ($decoded !== false) {
            return $decoded;
        }
    }

    if ($encoding === 4) {
        return quoted_printable_decode($content);
    }

    return $content;
}

function extract_plain_text_from_imap_message($inbox, int $messageNumber): string {
    $structure = @imap_fetchstructure($inbox, $messageNumber);

    if ($structure && !empty($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $partNo = (string) ($index + 1);
            $subtype = strtoupper((string) ($part->subtype ?? ''));
            $encoding = (int) ($part->encoding ?? 0);

            if ((int) ($part->type ?? -1) === 0 && $subtype === 'PLAIN') {
                $raw = (string) @imap_fetchbody($inbox, $messageNumber, $partNo);
                $text = decode_imap_part_content($raw, $encoding);
                $text = trim(str_replace("\0", '', $text));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        foreach ($structure->parts as $index => $part) {
            $partNo = (string) ($index + 1);
            $encoding = (int) ($part->encoding ?? 0);
            if ((int) ($part->type ?? -1) === 0) {
                $raw = (string) @imap_fetchbody($inbox, $messageNumber, $partNo);
                $text = decode_imap_part_content($raw, $encoding);
                $text = trim(strip_tags($text));
                if ($text !== '') {
                    return $text;
                }
            }
        }
    }

    $fallback = (string) @imap_body($inbox, $messageNumber);
    $fallback = trim(strip_tags(decode_imap_part_content($fallback, (int) ($structure->encoding ?? 0))));
    return $fallback;
}

function find_submission_id_by_sender_email(PDO $pdo, string $fromEmail): int {
    if ($fromEmail === '') {
        return 0;
    }

    $stmt = $pdo->prepare("\n        SELECT id\n        FROM contact_submissions\n        WHERE LOWER(email) = LOWER(:email)\n        ORDER BY created_at DESC, id DESC\n        LIMIT 1\n    ");
    $stmt->execute(['email' => $fromEmail]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : 0;
}

/**
 * Synchroniseer inkomende mailbox-berichten via IMAP naar contact_incoming.
 *
 * @return array{processed:int,imported:int,skipped:int}
 */
function sync_contact_inbox_imap(PDO $pdo, ?string &$errorMessage = null): array {
    $stats = ['processed' => 0, 'imported' => 0, 'skipped' => 0];

    if (!defined('IMAP')) {
        $errorMessage = 'IMAP configuratie ontbreekt.';
        return $stats;
    }

    $enabled = !empty(IMAP['ENABLED']);
    if (!$enabled) {
        $errorMessage = 'IMAP staat uit. Zet IMAP[ENABLED] op true in config.';
        return $stats;
    }

    if (!function_exists('imap_open')) {
        $errorMessage = 'PHP IMAP extensie ontbreekt. Activeer php_imap in php.ini.';
        return $stats;
    }

    $host = trim((string) (IMAP['HOST'] ?? ''));
    $port = (int) (IMAP['PORT'] ?? 993);
    $flags = trim((string) (IMAP['FLAGS'] ?? '/imap/ssl'));
    $mailbox = trim((string) (IMAP['MAILBOX'] ?? 'INBOX'));
    $user = trim((string) (IMAP['USER'] ?? ''));
    $pass = (string) (IMAP['PASS'] ?? '');
    $lookbackDays = max(1, (int) (IMAP['LOOKBACK_DAYS'] ?? 14));
    $maxFetch = max(1, min(500, (int) (IMAP['MAX_FETCH'] ?? 50)));

    if ($host === '' || $user === '' || $pass === '') {
        $errorMessage = 'IMAP config is incompleet (HOST/USER/PASS).';
        return $stats;
    }

    $mailboxPath = '{' . $host . ':' . $port . $flags . '}' . $mailbox;
    $inbox = @imap_open($mailboxPath, $user, $pass);

    if ($inbox === false) {
        $imapErr = imap_last_error();
        $errorMessage = 'IMAP login mislukt.' . ($imapErr ? ' ' . $imapErr : '');
        if (is_string($imapErr) && stripos($imapErr, 'Too many login failures') !== false) {
            $errorMessage = 'IMAP login tijdelijk geblokkeerd door te veel mislukte pogingen. Wacht 15-30 minuten, controleer mailbox IMAP-toegang in Microsoft 365 en test daarna opnieuw.';
        }
        return $stats;
    }

    try {
        $since = date('d-M-Y', strtotime('-' . $lookbackDays . ' days'));
        $messageNumbers = @imap_search($inbox, 'SINCE "' . $since . '"', SE_FREE, 'UTF-8');

        if (!is_array($messageNumbers) || $messageNumbers === []) {
            return $stats;
        }

        rsort($messageNumbers, SORT_NUMERIC);
        $messageNumbers = array_slice($messageNumbers, 0, $maxFetch);

        foreach ($messageNumbers as $messageNumber) {
            $stats['processed']++;
            $msgNo = (int) $messageNumber;

            $overviewList = @imap_fetch_overview($inbox, (string) $msgNo, 0);
            $overview = (is_array($overviewList) && isset($overviewList[0])) ? $overviewList[0] : null;
            if (!$overview) {
                $stats['skipped']++;
                continue;
            }

            $subject = decode_mime_header_value((string) ($overview->subject ?? ''));
            $fromRaw = (string) ($overview->from ?? '');
            [$fromName, $fromEmail] = parse_email_address($fromRaw);

            if (!is_email($fromEmail)) {
                $stats['skipped']++;
                continue;
            }

            $headers = (string) @imap_fetchheader($inbox, $msgNo);
            $messageId = normalize_message_identifier((string) ($overview->message_id ?? extract_header_value($headers, 'Message-ID')));
            $inReplyTo = normalize_message_identifier(extract_header_value($headers, 'In-Reply-To'));
            $references = trim(extract_header_value($headers, 'References'));
            $mailDate = (string) ($overview->date ?? '');
            $receivedAt = parse_mail_date_to_datetime($mailDate);
            $body = trim(extract_plain_text_from_imap_message($inbox, $msgNo));
            if ($body === '') {
                $body = '(geen tekstinhoud gedetecteerd)';
            }

            $sourceHash = hash(
                'sha256',
                strtolower($fromEmail) . '|' .
                mb_strtolower($subject, 'UTF-8') . '|' .
                ($messageId !== '' ? $messageId : $mailDate) . '|' .
                mb_substr($body, 0, 500, 'UTF-8')
            );

            $submissionId = find_submission_id_by_sender_email($pdo, $fromEmail);
            $inserted = create_contact_incoming($pdo, [
                'submission_id' => $submissionId,
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'subject' => $subject,
                'body' => $body,
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo,
                'references_header' => $references,
                'mail_date' => $mailDate,
                'received_at' => $receivedAt,
                'source_hash' => $sourceHash,
            ]);

            if ($inserted) {
                $stats['imported']++;
            } else {
                $stats['skipped']++;
            }
        }
    } finally {
        @imap_close($inbox);
    }

    return $stats;
}

/**
 * Insert/update postmeta value.
 */
function upsert_post_meta(PDO $pdo, int $postId, string $metaKey, string $metaValue): void {
    $metaStmt = $pdo->prepare("\n        SELECT meta_id\n        FROM postmeta\n        WHERE post_id = :post_id\n          AND meta_key = :meta_key\n        LIMIT 1\n    ");
    $metaStmt->execute([
        'post_id' => $postId,
        'meta_key' => $metaKey,
    ]);
    $metaId = $metaStmt->fetchColumn();

    if ($metaId !== false) {
        $update = $pdo->prepare("\n            UPDATE postmeta\n            SET meta_value = :meta_value\n            WHERE meta_id = :meta_id\n            LIMIT 1\n        ");
        $update->execute([
            'meta_value' => $metaValue,
            'meta_id' => (int) $metaId,
        ]);
        return;
    }

    $insert = $pdo->prepare("\n        INSERT INTO postmeta (post_id, meta_key, meta_value)\n        VALUES (:post_id, :meta_key, :meta_value)\n    ");
    $insert->execute([
        'post_id' => $postId,
        'meta_key' => $metaKey,
        'meta_value' => $metaValue,
    ]);
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