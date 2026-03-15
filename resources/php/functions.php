<?php

    declare(strict_types=1);

    require_once __DIR__ . '/init.php';

    // Start session when this file is included.
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Ensure Session and Cookie helpers are available.
    require_once __DIR__ . '/class_session.php';
    require_once __DIR__ . '/class_cookie.php';

    function mol_session(): Session
    {
        static $session = null;
        if ($session === null) {
            $session = new Session($GLOBALS['link'] ?? null);
        }
        return $session;
    }

    function mol_cookie(): Cookie
    {
        static $cookie = null;
        if ($cookie === null) {
            $cookie = new Cookie($GLOBALS['link'] ?? null);
        }
        return $cookie;
    }

    function mol_cookie_persist(int $userId, string $name, string $value, int $expires = 0): bool
    {
        return mol_cookie()->persist($userId, $name, $value, $expires);
    }

    function mol_cookie_retrieve_persisted(int $userId, string $name, $default = null)
    {
        return mol_cookie()->retrievePersisted($userId, $name, $default);
    }

    function mol_cookie_clear_persisted(int $userId, string $name, array $options = []): bool
    {
        return mol_cookie()->clearPersisted($userId, $name, $options);
    }

    /**
     * Escaping helpers
     */
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Safe redirect (absolute or relative).
     */
    function mol_safe_redirect(string $url): void
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
            $url = '/' . ltrim($url, '/');
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Helper to detect whether the request is over HTTPS.
     */
    function mol_is_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || cfg_env_bool('FORCE_HTTPS', false);
    }

    /**
     * Send baseline security headers for all pages.
     */
    function mol_send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        if (mol_is_https()) {
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        }
    }

    /**
     * Nonces for CSRF protection.
     */
    function mol_nonce_field(string $action): void
    {
        $nonce = bin2hex(random_bytes(16));
        $nonces = (array) mol_session()->get('nonces', []);
        $nonces[$action] = $nonce;
        mol_session()->set('nonces', $nonces);

        echo '<input type="hidden" name="_nonce" value="' . esc_attr($nonce) . '">';
        echo '<input type="hidden" name="_nonce_action" value="' . esc_attr($action) . '">';
    }

    function mol_require_valid_nonce(string $action, ?array $jsonBody = null): bool
    {
        // Allow nonce fields to come via JSON body (for AJAX requests) or via headers.
        // If the request body has already been read by the caller, they can pass it in.
        static $cachedJson = null;

        if ($jsonBody === null) {
            if ($cachedJson === null) {
                $rawBody = file_get_contents('php://input');
                $cachedJson = is_string($rawBody) ? json_decode($rawBody, true) : null;
            }
            $jsonBody = $cachedJson;
        }

        $postedAction = $_POST['_nonce_action']
            ?? ($jsonBody['_nonce_action'] ?? null)
            ?? $_SERVER['HTTP_X_CSRF_ACTION']
            ?? '';

        $postedNonce = $_POST['_nonce']
            ?? ($jsonBody['_nonce'] ?? null)
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if ($postedAction !== $action) {
            return false;
        }

        $nonces = (array) mol_session()->get('nonces', []);
        $stored = $nonces[$action] ?? null;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        $valid = hash_equals($stored, (string) $postedNonce);
        if ($action !== 'global_csrf') {
            unset($nonces[$action]);
            mol_session()->set('nonces', $nonces);
        }
        return $valid;
    }

    function mol_get_nonce(string $action): string
    {
        $nonce = bin2hex(random_bytes(16));
        $nonces = (array) mol_session()->get('nonces', []);
        $nonces[$action] = $nonce;
        mol_session()->set('nonces', $nonces);
        return $nonce;
    }

    /**
     * Get or generate the current global CSRF token.
     */
    function mol_csrf_token(): string
    {
        $action = 'global_csrf';
        $nonce = (string) (mol_session()->get('nonces', [])[$action] ?? '');
        if ($nonce !== '') {
            return $nonce;
        }
        return mol_get_nonce($action);
    }

    /**
     * Echo the hidden inputs used for CSRF protection (similar to Laravel's @csrf).
     */
    function mol_csrf_field(): void
    {
        $token = esc_attr(mol_csrf_token());
        echo "<input type=\"hidden\" name=\"_nonce_action\" value=\"global_csrf\">";
        echo "<input type=\"hidden\" name=\"_nonce\" value=\"{$token}\">";
    }

    /**
     * Verify CSRF token on state-changing requests (POST/PUT/PATCH/DELETE).
     * Returns true when the request is safe or token is valid.
     * If invalid, this will send a 403 response and terminate execution.
     */
    function mol_csrf_protect(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // When called globally, avoid consuming the request body (which may be read later by JSON endpoints).
        // The frontend should send CSRF tokens via headers for AJAX requests.
        if (mol_require_valid_nonce('global_csrf', [])) {
            return true;
        }

        http_response_code(403);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || str_contains($_SERVER['REQUEST_URI'] ?? '', '/resources/ajax/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid CSRF token']);
        } else {
            echo 'Invalid CSRF token';
        }
        exit;
    }

    /**
     * Automatically inject CSRF hidden fields into POST forms.
     *
     * This makes it unnecessary to add `<?php mol_csrf_field(); ?>` in every template.
     */
    function mol_auto_csrf_in_forms(string $buffer): string
    {
        // Don't touch non-HTML responses.
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0 && stripos($header, 'application/json') !== false) {
                return $buffer;
            }
        }

        $trimmed = ltrim($buffer);
        if ($trimmed === '' || $trimmed[0] === '{' || $trimmed[0] === '[' || str_starts_with($trimmed, '<?xml')) {
            return $buffer;
        }

        if (stripos($buffer, '<form') === false) {
            return $buffer;
        }

        $tokenHtml = '<input type="hidden" name="_nonce_action" value="global_csrf"><input type="hidden" name="_nonce" value="' . esc_attr(mol_csrf_token()) . '">';

        $pattern = '/(<form\b[^>]*\bmethod\s*=\s*(?:"post"|\'post\'|post)[^>]*>)/i';
        return preg_replace_callback($pattern, function ($matches) use ($tokenHtml) {
            $formOpen = $matches[1];
            if (stripos($formOpen, '_nonce_action') !== false || stripos($formOpen, '_nonce') !== false) {
                return $formOpen;
            }
            return $formOpen . $tokenHtml;
        }, $buffer);
    }

    /**
     * Validate a password against the current password policy.
     *
     * @param string $password
     * @return string|null Returns null if valid, otherwise returns an error message.
     */
    function mol_validate_password(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Wachtwoord moet minstens 8 tekens lang zijn.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Wachtwoord moet minimaal één hoofdletter bevatten.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Wachtwoord moet minimaal één kleine letter bevatten.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Wachtwoord moet minimaal één cijfer bevatten.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Wachtwoord moet minimaal één speciaal teken bevatten.';
        }
        return null;
    }

    /**
     * Flash messaging helpers.
     */
    function flash(string $key, $value): void
    {
        mol_session()->flash($key, $value);
    }

    function flashGet(string $key, $default = null)
    {
        return mol_session()->flashGet($key, $default);
    }

    /**
     * Return a Gravatar URL for the given email.
     */
    function mol_gravatar_url(string $email, int $size = 80, string $default = 'identicon'): string
    {
        $email = trim(strtolower($email));
        $hash = md5($email);
        $size = max(1, min(512, $size));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=" . urlencode($default);
    }

    /**
     * Post type registry (similar to WordPress register_post_type()).
     *
     * @var array<string, array> $mol_post_types
     */
    $mol_post_types = [];

    /**
     * Register a post type.
     *
     * @param string $name
     * @param array  $args
     */
    function mol_register_post_type(string $name, array $args = []): void
    {
        global $mol_post_types;

        $defaults = [
            'labels' => [
                'singular' => ucfirst($name),
                'plural' => ucfirst($name) . 's',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'content'],
            'taxonomies' => [],
        ];

        $mol_post_types[$name] = array_merge($defaults, $args);
    }

    /**
     * Get post type config stored in the database.
     *
     * @return array<string, array>
     */
    function mol_get_post_types_config(): array
    {
        try {
            $account = new Account($GLOBALS['link']);
            $raw = $account->get_user_meta(0, 'post_types_config');
            if (!$raw) {
                return [];
            }
            $data = json_decode((string) $raw, true);
            if (!is_array($data)) {
                return [];
            }

            // Make sure each post type has a menu_order for sorting.
            foreach ($data as $name => &$config) {
                if (!is_array($config)) {
                    continue;
                }
                if (!isset($config['menu_order']) || !is_int($config['menu_order'])) {
                    $config['menu_order'] = 0;
                }
                if (!isset($config['menu_icon']) || !is_string($config['menu_icon'])) {
                    $config['menu_icon'] = '';
                }
            }

            return $data;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Store post type config in the database.
     *
     * @param array<string, array> $config
     */
    function mol_set_post_types_config(array $config): bool
    {
        try {
            // Ensure ordering / icon defaults are present.
            foreach ($config as $name => &$item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!isset($item['menu_order']) || !is_int($item['menu_order'])) {
                    $item['menu_order'] = 0;
                }
                if (!isset($item['menu_icon']) || !is_string($item['menu_icon'])) {
                    $item['menu_icon'] = '';
                }
            }

            $account = new Account($GLOBALS['link']);
            return $account->set_user_meta(0, 'post_types_config', json_encode($config, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Get all registered post types.
     *
     * @return array<string, array>
     */
    function mol_get_post_types(): array
    {
        global $mol_post_types;

        $types = $mol_post_types;
        $config = mol_get_post_types_config();
        if (is_array($config)) {
            foreach ($config as $name => $args) {
                if (!is_string($name) || !is_array($args)) {
                    continue;
                }
                $types[$name] = array_merge($types[$name] ?? [], $args);
            }
        }

        // Ensure supports + taxonomies are always arrays.
        foreach ($types as $name => $args) {
            if (!isset($args['supports']) || !is_array($args['supports'])) {
                $types[$name]['supports'] = ['title', 'content'];
            }
            if (!isset($args['taxonomies']) || !is_array($args['taxonomies'])) {
                $types[$name]['taxonomies'] = [];
            }
            if (!isset($args['menu_order']) || !is_int($args['menu_order'])) {
                $types[$name]['menu_order'] = 0;
            }
            if (!isset($args['menu_icon']) || !is_string($args['menu_icon'])) {
                $types[$name]['menu_icon'] = '';
            }
        }

        // Sort by menu_order then by name.
        uasort($types, function ($a, $b) {
            $orderA = $a['menu_order'] ?? 0;
            $orderB = $b['menu_order'] ?? 0;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            return strcmp($a['labels']['plural'] ?? '', $b['labels']['plural'] ?? '');
        });

        return $types;
    }

    /**
     * Get a registered post type.
     */
    function mol_get_post_type(string $name): ?array
    {
        $types = mol_get_post_types();
        return $types[$name] ?? null;
    }

    /**
     * Get supported features for a post type.
     *
     * @param string $postType
     * @return string[]
     */
    function mol_get_post_type_supports(string $postType): array
    {
        $type = mol_get_post_type($postType);
        return is_array($type['supports'] ?? null) ? $type['supports'] : [];
    }

    /**
     * Get taxonomies registered for a post type.
     *
     * @param string $postType
     * @return string[]
     */
    function mol_get_post_type_taxonomies(string $postType): array
    {
        $type = mol_get_post_type($postType);
        return is_array($type['taxonomies'] ?? null) ? $type['taxonomies'] : [];
    }

    /**
     * Determine if a post type is registered.
     */
    function mol_is_post_type(string $name): bool
    {
        $types = mol_get_post_types();
        return array_key_exists($name, $types);
    }

    /**
     * Get all meta for a post.
     *
     * @return array<string, mixed>
     */
    function mol_get_post_meta(int $postId): array
    {
        $rows = get_results(
            'SELECT pm.meta_key, pm.meta_value
             FROM ' . table('postmeta') . ' pm
             WHERE pm.post_id = :post_id',
            ['post_id' => $postId]
        );

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['meta_key']] = $row['meta_value'];
        }
        return $meta;
    }

    /**
     * Update or add post meta.
     */
    function mol_update_post_meta(int $postId, string $key, $value): bool
    {
        // Try update first.
        $ok = update('postmeta', ['meta_value' => $value], ['post_id' => $postId, 'meta_key' => $key]);
        if ($ok) {
            return true;
        }

        // Insert if update didn't affect any rows.
        return insert('postmeta', ['post_id' => $postId, 'meta_key' => $key, 'meta_value' => $value]);
    }

    /**
     * Delete post meta.
     */
    function mol_delete_post_meta(int $postId, string $key): bool
    {
        return delete('postmeta', ['post_id' => $postId, 'meta_key' => $key]);
    }

    /**
     * Render blocks stored in post_content.
     * Supports a simple block schema similar to Gutenberg.
     *
     * @param array<int, array> $blocks
     */
    function mol_render_blocks(array $blocks): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['type'])) {
                continue;
            }

            switch ($block['type']) {
                case 'heading':
                    $text = $block['text'] ?? '';
                    echo '<h2>' . esc_html($text) . '</h2>';
                    break;
                case 'image':
                    $url = $block['url'] ?? '';
                    $alt = $block['alt'] ?? '';
                    echo '<div class="block-image"><img src="' . esc_attr($url) . '" alt="' . esc_attr($alt) . '" /></div>';
                    break;
                case 'paragraph':
                default:
                    $text = $block['text'] ?? '';
                    echo '<p>' . esc_html($text) . '</p>';
                    break;
            }
        }
    }

    /**
     * Render a post's content field, supporting both raw HTML and block JSON.
     */
    function mol_render_post_content(string $content): void
    {
        $blocks = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($blocks)) {
            mol_render_blocks($blocks);
            return;
        }

        echo $content;
    }

    /**
     * Get terms for a taxonomy.
     *
     * @param string $taxonomy
     * @return array<int, array>
     */
    function mol_get_terms(string $taxonomy = 'category'): array
    {
        return get_results(
            'SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description
             FROM ' . table('terms') . ' t
             JOIN ' . table('term_taxonomy') . ' tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = :taxonomy',
            ['taxonomy' => $taxonomy]
        );
    }

    /**
     * Get terms associated with a post.
     *
     * @param int $postId
     * @param string|null $taxonomy
     * @return array<int, array>
     */
    function mol_get_post_terms(int $postId, ?string $taxonomy = null): array
    {
        $sql = 'SELECT t.term_id, t.name, t.slug, tt.taxonomy
                FROM ' . table('terms') . ' t
                JOIN ' . table('term_taxonomy') . ' tt ON tt.term_id = t.term_id
                JOIN ' . table('term_relationships') . ' tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tr.object_id = :post_id';

        $params = ['post_id' => $postId];
        if ($taxonomy !== null) {
            $sql .= ' AND tt.taxonomy = :taxonomy';
            $params['taxonomy'] = $taxonomy;
        }

        return get_results($sql, $params);
    }

    /**
     * Set terms for a post (replace existing).
     *
     * @param int $postId
     * @param string $taxonomy
     * @param int[] $termIds
     */
    function mol_set_post_terms(int $postId, string $taxonomy, array $termIds): bool
    {
        // Ensure all requested terms exist and belong to taxonomy.
        $placeholders = implode(',', array_fill(0, count($termIds), '?'));
        $sql = 'SELECT term_taxonomy_id, term_id
                FROM ' . table('term_taxonomy') . '
                WHERE taxonomy = ? AND term_id IN (' . $placeholders . ')';
        $params = array_merge([$taxonomy], $termIds);
        $rows = get_results($sql, $params);

        $validTermTaxonomyIds = array_column($rows, 'term_taxonomy_id');

        // Clear existing relationships for this post + taxonomy
        $termTaxonomyIds = get_results(
            'SELECT tt.term_taxonomy_id
             FROM ' . table('term_taxonomy') . ' tt
             JOIN ' . table('term_relationships') . ' tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tr.object_id = :post_id AND tt.taxonomy = :taxonomy',
            ['post_id' => $postId, 'taxonomy' => $taxonomy]
        );

        foreach ($termTaxonomyIds as $row) {
            delete('term_relationships', ['object_id' => $postId, 'term_taxonomy_id' => $row['term_taxonomy_id']]);
        }

        // Insert new relationships.
        foreach ($validTermTaxonomyIds as $termTaxonomyId) {
            insert('term_relationships', ['object_id' => $postId, 'term_taxonomy_id' => $termTaxonomyId, 'term_order' => 0]);
        }

        // Update term counts for this taxonomy.
        mol_update_term_count($taxonomy);

        return true;
    }

    /**
     * Get or create a term by slug.
     */
    function mol_get_or_create_term(string $name, string $slug, string $taxonomy): ?array
    {
        $existing = get_row(
            'SELECT t.term_id, tt.term_taxonomy_id
             FROM ' . table('terms') . ' t
             JOIN ' . table('term_taxonomy') . ' tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = :taxonomy AND t.slug = :slug',
            ['taxonomy' => $taxonomy, 'slug' => $slug]
        );

        if ($existing) {
            return $existing;
        }

        insert('terms', ['name' => $name, 'slug' => $slug]);
        $termId = (int) get_var('SELECT LAST_INSERT_ID()');
        insert('term_taxonomy', ['term_id' => $termId, 'taxonomy' => $taxonomy, 'description' => '', 'parent' => 0, 'count' => 0]);
        $termTaxonomyId = (int) get_var('SELECT LAST_INSERT_ID()');

        mol_update_term_count($taxonomy);

        return ['term_id' => $termId, 'term_taxonomy_id' => $termTaxonomyId];
    }

    /**
     * Update term count summary.
     */
    function mol_update_term_count(string $taxonomy): void
    {
        $terms = mol_get_terms($taxonomy);
        foreach ($terms as $term) {
            $count = (int) get_var(
                'SELECT COUNT(*) FROM ' . table('term_relationships') . ' tr
                 JOIN ' . table('term_taxonomy') . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tt.taxonomy = :taxonomy AND tt.term_id = :term_id',
                ['taxonomy' => $taxonomy, 'term_id' => $term['term_id']]
            );
            update('term_taxonomy', ['count' => $count], ['term_taxonomy_id' => $term['term_taxonomy_id']]);
        }
    }

    /**
     * Get posts with basic filtering options.
     *
     * @param array $args [
     *   'post_type' => string,
     *   'status' => string,
     *   'slug' => string,
     *   'limit' => int,
     *   'offset' => int,
     * ]
     *
     * @return array<int, array>
     */
    function mol_get_posts(array $args = []): array
    {
        $postType = trim((string) ($args['post_type'] ?? 'post'));
        $status = trim((string) ($args['status'] ?? 'publish'));
        $slug = trim((string) ($args['slug'] ?? ''));
        $limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 20;
        $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

        $where = [];
        $params = [];

        if ($postType !== '') {
            $where[] = 'post_type = :post_type';
            $params['post_type'] = $postType;
        }
        if ($status !== '') {
            $where[] = 'post_status = :status';
            $params['status'] = $status;
        }
        if ($slug !== '') {
            $where[] = 'post_name = :post_name';
            $params['post_name'] = $slug;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = 'SELECT * FROM ' . table('posts') . ' ' . $whereClause . ' ORDER BY post_date DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        // PDO doesn't accept named params for LIMIT/OFFSET reliably; bind separately.
        $stmt = $GLOBALS['link']->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * Get a single post by slug and type.
     */
    function mol_get_post_by_slug(string $slug, string $postType = 'post'): ?array
    {
        return get_row(
            'SELECT * FROM ' . table('posts') . ' WHERE post_name = :slug AND post_type = :post_type LIMIT 1',
            ['slug' => $slug, 'post_type' => $postType]
        );
    }

    /**
     * Get the default role/capability map.
     *
     * @return array<string, string[]>
     */
    function mol_default_role_capabilities(): array
    {
        return [
            'admin' => ['view_users', 'manage_users', 'view_audit_log', 'edit_roles', 'edit_permissions', 'manage_post_types'],
            'editor' => ['view_users'],
            'user' => [],
        ];
    }

    /**
     * Read role/capability configuration from a global setting stored in usermeta.
     * This allows admins to adjust roles without modifying code.
     *
     * @return array<string, string[]>
     */
    function mol_get_roles_config(): array
    {
        try {
            $account = new Account($GLOBALS['link']);
            $raw = $account->get_user_meta(0, 'roles_config');
            if (!$raw) {
                return [];
            }
            $data = json_decode((string) $raw, true);
            if (!is_array($data)) {
                return [];
            }
            return $data;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Store role/capability configuration for the application.
     *
     * @param array<string, string[]> $config
     */
    function mol_set_roles_config(array $config): bool
    {
        try {
            $account = new Account($GLOBALS['link']);
            return $account->set_user_meta(0, 'roles_config', json_encode($config, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Role-based capabilities map.
     * Add/adjust capabilities as needed for your RBAC model.
     *
     * @return array<string, string[]>
     */
    function mol_role_capabilities(): array
    {
        $config = mol_get_roles_config();
        if (!empty($config)) {
            return $config;
        }

        return mol_default_role_capabilities();
    }

    function mol_role_has_capability(string $role, string $capability): bool
    {
        $caps = mol_role_capabilities();
        return in_array($capability, $caps[$role] ?? [], true);
    }

    function mol_current_user_can(string $capability): bool
    {
        $userId = mol_session()->get('user_id');
        if (empty($userId) || !is_int($userId)) {
            return false;
        }

        $account = new Account($GLOBALS['link']);
        $user = $account->get_user_by_id((int) $userId);
        if (!$user) {
            return false;
        }

        return mol_role_has_capability((string) ($user['user_role'] ?? ''), $capability);
    }

    function mol_audit(int $actorId, ?int $targetId, string $action, array $meta = []): void
    {
        $metaJson = json_encode($meta, JSON_THROW_ON_ERROR);
        insert('audit_log', [
            'actor_id' => $actorId,
            'target_id' => $targetId,
            'action' => $action,
            'meta' => $metaJson,
        ]);
    }

    /**
     * Audit log helper.
     *
     * @param int         $actorId  The user who performed the action.
     * @param int|null    $targetId The target user (optional).
     * @param string      $action   A short action identifier (e.g. "user_created").
     * @param array       $meta     Optional additional context.
     */
    function mol_audit_log(int $actorId, ?int $targetId, string $action, array $meta = []): bool
    {
        try {
            return insert('audit_log', [
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'action' => $action,
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Audit log is best-effort; don't block user operations if it fails.
            $msg = $e->getMessage();
            if (strpos($msg, "doesn't exist") !== false || strpos($msg, '1146') !== false) {
                // Try creating the audit_log table automatically.
                @db_query(
                    "CREATE TABLE IF NOT EXISTS " . table('audit_log') . " (
                        id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
                        actor_id bigint UNSIGNED NOT NULL,
                        target_id bigint UNSIGNED DEFAULT NULL,
                        action varchar(100) NOT NULL,
                        meta text,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY actor_id (actor_id),
                        KEY target_id (target_id),
                        KEY action (action)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
                );

                try {
                    return insert('audit_log', [
                        'actor_id' => $actorId,
                        'target_id' => $targetId,
                        'action' => $action,
                        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable $e2) {
                    error_log('Audit log failed after creating table: ' . $e2->getMessage());
                    return false;
                }
            }

            error_log('Audit log failed: ' . $msg);
            return false;
        }
    }

    /**
     * Send an email using PHPMailer (SMTP) when available.
     *
     * @param string       $recipient
     * @param string       $subject
     * @param string       $message
     * @param string       $from
     * @param string       $from_name
     * @param string       $reply_to
     * @param string       $reply_to_name
     * @param string|array $cc
     * @param string|array $bcc
     * @param string|array $attachments
     *
     * @return true|string True on success or error message on failure.
     */
    function email(
        $recipient,
        $subject,
        $message,
        $from = MAIL['FROM'],
        $from_name = MAIL['NAME'],
        $reply_to = '',
        $reply_to_name = '',
        $cc = '',
        $bcc = '',
        $attachments = ''
    ) {
        $debug = MAIL['DEBUG'] ?? 0;

        // Always log that email() was invoked so we can confirm execution.
        @file_put_contents(__DIR__ . '/../../email-called.log', '[' . date('c') . "] called: {$recipient} subject={$subject} debug={$debug}\n", FILE_APPEND | LOCK_EX);

        // Ensure consistent types for cc/bcc/attachments
        $ccArray = is_array($cc) ? $cc : ($cc !== '' ? [$cc] : []);
        $bccArray = is_array($bcc) ? $bcc : ($bcc !== '' ? [$bcc] : []);
        $attachmentsArray = is_array($attachments) ? $attachments : ($attachments !== '' ? [$attachments] : []);

        $logEntry = '[' . date('c') . "] To: {$recipient} Subject: {$subject}\n{$message}\n";

        // Render HTML email template (fallback to plain text if not needed)
        $htmlMessage = email_render_template(
            $subject,
            '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
        );

        // Try using PHPMailer (SMTP) if available
        $mailerLoaded = false;
        $mailerError = null;

        if (file_exists(DIR . 'resources/includes/phpmailer/src/PHPMailer.php')) {
            require_once DIR . 'resources/includes/phpmailer/src/PHPMailer.php';
            require_once DIR . 'resources/includes/phpmailer/src/SMTP.php';
            require_once DIR . 'resources/includes/phpmailer/src/Exception.php';

            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = MAIL['HOST'] ?? '';
                $mail->SMTPAuth = true;
                $mail->Username = MAIL['USER'] ?? '';
                $mail->Password = MAIL['PASS'] ?? '';
                $mail->SMTPSecure = MAIL['SECURE'] ?? '';
                $mail->Port = (int) (MAIL['PORT'] ?? 587);
                $mail->CharSet = 'UTF-8';

                if ($debug) {
                    $mail->SMTPDebug = 0;
                    if ($debug >= 2) {
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = function ($str) use (&$logEntry) {
                            $logEntry .= $str;
                        };
                    }
                }

                // Ensure the envelope sender matches the authenticated SMTP user, to prevent
                // SendAsDenied/DMARC failures for custom From domains.
                $envelopeFrom = MAIL['USER'] ?? $from;
                $mail->setFrom($envelopeFrom, $from_name);
                $mail->Sender = $envelopeFrom; // envelope-from (used for SMTP MAIL FROM)
                $mail->addAddress($recipient);

                // If the desired From address differs from the authenticated SMTP account,
                // set it as Reply-To so replies go to the expected address.
                if ($from !== $envelopeFrom && $from !== '') {
                    $mail->addReplyTo($from, $from_name);

                    // Log this mismatch so we can troubleshoot custom domain sending.
                    $warning = sprintf(
                        "[%s] INFO: SMTP user '%s' differs from From '%s' - using Reply-To.\n",
                        date('c'),
                        $envelopeFrom,
                        $from
                    );
                    @file_put_contents(DIR . 'mail.log', $warning, FILE_APPEND | LOCK_EX);
                    @file_put_contents(DIR . 'resources/mail.log', $warning, FILE_APPEND | LOCK_EX);
                }

                if ($reply_to !== '') {
                    $mail->addReplyTo($reply_to, $reply_to_name);
                }

                // Optional DKIM signing when configured
                if (!empty(MAIL['DKIM_DOMAIN']) && !empty(MAIL['DKIM_SELECTOR']) && !empty(MAIL['DKIM_PRIVATE_KEY'])) {
                    $mail->DKIM_domain = MAIL['DKIM_DOMAIN'];
                    $mail->DKIM_selector = MAIL['DKIM_SELECTOR'];
                    $mail->DKIM_private = MAIL['DKIM_PRIVATE_KEY'];
                    if (!empty(MAIL['DKIM_PASS'])) {
                        $mail->DKIM_passphrase = MAIL['DKIM_PASS'];
                    }
                }

                foreach ($ccArray as $c) {
                    if ($c) {
                        $mail->addCC($c);
                    }
                }

                foreach ($bccArray as $b) {
                    if ($b) {
                        $mail->addBCC($b);
                    }
                }

                foreach ($attachmentsArray as $a) {
                    if ($a) {
                        $mail->addAttachment($a);
                    }
                }

                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $htmlMessage;
                $mail->AltBody = strip_tags($message);

                // SSL verification settings (useful for shared hosting/mismatched TLS names)
                if (empty(MAIL['VERIFY_SSL'])) {
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                }

                // Always attempt to send, even when debug output is enabled.
                // Debug mode 2 will still log SMTP conversation (if available).
                $mailerLoaded = $mail->send();
            } catch (Exception $e) {
                $mailerError = $e->getMessage();
            }
        }

        // Always log when debug is enabled.
        // Write to both the project root and a known-writable directory.
        if ($debug) {
            @file_put_contents(DIR . 'mail.log', $logEntry, FILE_APPEND | LOCK_EX);
            @file_put_contents(DIR . 'resources/mail.log', $logEntry, FILE_APPEND | LOCK_EX);
        }

        if ($mailerLoaded === true) {
            return true;
        }

        // Fall back to PHP mail() if PHPMailer not available or send failed.
        if ($mailerError) {
            $logEntry .= "PHPMailer error: {$mailerError}\n";
        }

        $headers = [];
        if ($from !== '') {
            $headers[] = 'From: ' . (trim($from_name) ? sprintf('%s <%s>', $from_name, $from) : $from);
        }
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . (trim($reply_to_name) ? sprintf('%s <%s>', $reply_to_name, $reply_to) : $reply_to);
        }
        if (!empty($ccArray)) {
            $headers[] = 'Cc: ' . implode(', ', $ccArray);
        }
        if (!empty($bccArray)) {
            $headers[] = 'Bcc: ' . implode(', ', $bccArray);
        }
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $envelopeFrom = MAIL['USER'] ?? $from;
        $additionalParams = '';
        if ($envelopeFrom) {
            $additionalParams = '-f' . escapeshellarg($envelopeFrom);
        }

        $sent = mail($recipient, $subject, $message, implode("\r\n", $headers), $additionalParams);
        if ($sent === true) {
            return true;
        }

        $error = error_get_last();
        return $mailerError ?: ($error['message'] ?? 'Unknown mail error');
    }

    /**
     * Simple rate limiting stored in session.
     *
     * @param string $key       Unique key per endpoint (e.g. "api_auth").
     * @param int    $limit     Maximum number of requests.
     * @param int    $windowSec Time window in seconds.
     *
     * @return array{allowed:bool, remaining:int, reset:int}
     */
    function mol_rate_limit(string $key, int $limit, int $windowSec): array
    {
        $now = time();
        $rateLimit = (array) mol_session()->get('rate_limit', []);
        $entry = $rateLimit[$key] ?? ['count' => 0, 'start' => $now];

        if (!isset($entry['count'], $entry['start']) || !is_int($entry['count']) || !is_int($entry['start'])) {
            $entry = ['count' => 0, 'start' => $now];
        }

        if ($now - $entry['start'] >= $windowSec) {
            $entry = ['count' => 0, 'start' => $now];
        }

        $entry['count'] += 1;
        $rateLimit[$key] = $entry;
        mol_session()->set('rate_limit', $rateLimit);

        $remaining = max(0, $limit - $entry['count']);
        $reset = $entry['start'] + $windowSec;

        return [
            'allowed' => $entry['count'] <= $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }

    /**
     * Block an IP address for a period of time.
     *
     * @param string      $ip
     * @param string      $reason
     * @param int|null    $expiresAt Timestamp when block expires (null = permanent)
     */
    /**
     * Build the usermeta key used for storing IP blocks.
     */
    function mol_ip_block_meta_key(string $ip): string
    {
        return 'ip_blocklist_' . md5($ip);
    }

    function mol_block_ip(string $ip, string $reason = '', ?int $expiresAt = null): bool
    {
        if (empty($ip)) {
            return false;
        }

        $key = mol_ip_block_meta_key($ip);
        $payload = json_encode([
            'ip' => $ip,
            'reason' => $reason,
            'expires_at' => $expiresAt ? date('c', $expiresAt) : null,
        ], JSON_THROW_ON_ERROR);

        try {
            $account = new Account($GLOBALS['link']);
            return $account->set_user_meta(0, $key, $payload);
        } catch (Throwable $e) {
            return false;
        }
    }

    function mol_unblock_ip(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $key = mol_ip_block_meta_key($ip);
        try {
            $account = new Account($GLOBALS['link']);
            return $account->delete_user_meta(0, $key);
        } catch (Throwable $e) {
            return false;
        }
    }

    function mol_is_ip_blocked(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $key = mol_ip_block_meta_key($ip);
        try {
            $account = new Account($GLOBALS['link']);
            $raw = $account->get_user_meta(0, $key);
        } catch (Throwable $e) {
            $raw = null;
        }

        if ($raw) {
            $data = json_decode((string) $raw, true);
            if (is_array($data)) {
                $expires = $data['expires_at'] ?? null;
                if (!$expires) {
                    return true;
                }

                if (strtotime($expires) < time()) {
                    // Cleanup expired block.
                    mol_unblock_ip($ip);
                    return false;
                }

                return true;
            }
        }

        // Fallback to legacy table-based blocklist (if it exists).
        try {
            $row = get_row(
                'SELECT expires_at FROM ' . table('ip_blocklist') . ' WHERE ip = :ip LIMIT 1',
                ['ip' => $ip]
            );
        } catch (Throwable $e) {
            return false;
        }

        if (!$row) {
            return false;
        }

        $expires = $row['expires_at'] ?? null;
        if (!$expires) {
            return true;
        }

        if (strtotime($expires) < time()) {
            mol_unblock_ip($ip);
            return false;
        }

        return true;
    }

    /**
     * Prune audit log entries older than the given number of days.
     */
    function mol_prune_audit_log(int $days = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        return db_query(
            'DELETE FROM ' . table('audit_log') . ' WHERE created_at < :cutoff',
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Authentication helpers (legacy `mol_` function names used in dashboard).
     */
    function is_user_logged_in(): bool
    {
        $userId = mol_session()->get('user_id');
        return !empty($userId) && is_int($userId) && $userId > 0;
    }

    function mol_signon(array $credentials)
    {
        global $link;

        $auth = new Auth($link);
        $user = $auth->login(
            (string) ($credentials['user_login'] ?? ''),
            (string) ($credentials['user_password'] ?? ''),
            !empty($credentials['remember'])
        );

        return $user;
    }

    function mol_logout(): void
    {
        global $link;

        // Ensure the current session is properly invalidated server-side (including
        // removing it from the user's active sessions list).
        if (isset($link)) {
            $auth = new Auth($link);
            $auth->logout();
            return;
        }

        // Fallback: destroy PHP session only.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Minimal helper to read JSON request body.
     */
    function mol_get_json_body(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
