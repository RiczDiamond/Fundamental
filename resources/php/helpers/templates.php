<?php

declare(strict_types=1);

// Basic template loader + simple content fetch helpers

function templates_dir(): string
{
    return rtrim(DIR_WEBSITE, '/') . '/templates/';
}

function load_template(string $name, array $context = []): void
{
    $file = templates_dir() . $name . '.php';

    if (!file_exists($file)) {
        // fallback to index
        $file = templates_dir() . 'index.php';
    }

    extract($context, EXTR_SKIP);
    require $file;
}

// Basic detection: maps $url segments to a template name and context
function detect_template_from_url(array $url): array
{
    // default
    $result = ['template' => 'index', 'context' => []];

    // search
    if (isset($url[0]) && $url[0] === 'search') {
        $result['template'] = 'search';
        $result['context']['query'] = $_GET['s'] ?? ($url[1] ?? '');
        $result['context']['posts'] = search_posts($result['context']['query']);
        return $result;
    }

    // category/author archives
    if (isset($url[0]) && ($url[0] === 'category' || $url[0] === 'author') && isset($url[1])) {
        $result['template'] = $url[0];
        $result['context'][$url[0]] = $url[1];
        $result['context']['posts'] = get_posts_by_term($url[0], $url[1]);
        return $result;
    }

    // single segment (home, page slug or post slug)
    if (!isset($url[0]) || $url[0] === '' || $url[0] === 'home') {
        $result['template'] = 'index';
        $result['context']['posts'] = get_posts(10);
        return $result;
    }

    // check page (prefer posts table with post_type='page' if available)
    $slug = $url[0];
    $page = get_page_by_slug($slug);
    if ($page) {
        $result['template'] = 'page';
        $result['context']['page'] = $page;
        return $result;
    }

    // check post
    $post = get_post_by_slug($slug);
    if ($post) {
        $result['template'] = 'single';
        $result['context']['post'] = $post;
        return $result;
    }

    // fallback: 404
    $result['template'] = 'index';
    $result['context']['posts'] = [];
    $result['context']['not_found'] = true;

    return $result;
}

// --- Data helpers (best-effort; will return null/empty on DB errors) ---

function get_page_by_slug(string $slug)
{
    global $link;

    if (!isset($link)) {
        return null;
    }

    try {
        // Try lookup by `slug` column first (if it exists), otherwise fall back to `post_name`
        $row = null;
        try {
            $stmt = $link->prepare('SELECT * FROM posts WHERE slug = :slug AND post_type = :type LIMIT 1');
            $stmt->execute([':slug' => $slug, ':type' => 'page']);
            $row = $stmt->fetch() ?: null;
        } catch (Exception $e) {
            // ignore - column may not exist
        }

        if (!$row) {
            try {
                $stmt = $link->prepare('SELECT * FROM posts WHERE post_name = :slug AND post_type = :type LIMIT 1');
                $stmt->execute([':slug' => $slug, ':type' => 'page']);
                $row = $stmt->fetch() ?: null;
            } catch (Exception $e) {
                return null;
            }
        }

        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function get_post_by_slug(string $slug)
{
    global $link;

    if (!isset($link)) {
        return null;
    }

    try {
        // Try lookup by `slug` column first; if that fails, try `post_name`.
        $row = null;
        try {
            $stmt = $link->prepare('SELECT * FROM posts WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch() ?: null;
        } catch (Exception $e) {
            // ignore - column may not exist
        }

        if (!$row) {
            try {
                $stmt = $link->prepare('SELECT * FROM posts WHERE post_name = :slug LIMIT 1');
                $stmt->execute([':slug' => $slug]);
                $row = $stmt->fetch() ?: null;
            } catch (Exception $e) {
                return null;
            }
        }

        if (!$row) {
            return null;
        }

        // attach taxonomy terms if WP-like tables exist
        try {
            $tstmt = $link->prepare('SELECT t.name, t.slug, tt.taxonomy FROM terms t JOIN term_taxonomy tt ON tt.term_id = t.term_id JOIN term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = :id');
            $id = $row['ID'] ?? $row['id'] ?? null;
            $tstmt->execute([':id' => $id]);
            $row['terms'] = $tstmt->fetchAll();
        } catch (Exception $e) {
            $row['terms'] = [];
        }

        return $row;
    } catch (Exception $e) {
        return null;
    }
}

function get_posts(int $limit = 10, string $post_type = 'post'): array
{
    global $link;
    if (!isset($link)) {
        return [];
    }

    try {
        // Some PDO drivers don't support binding LIMIT as a parameter when emulation is disabled.
        // Safer to inject the integer-casted limit directly after validation.
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 10;
        }
        // Prefer to filter by post_type when the column exists; fall back if not.
        $sql = 'SELECT * FROM posts';
        $params = [];
        $useType = true;
        try {
            // attempt a prepared statement using post_type
            // prefer ordering by `post_date` (some schemas use post_date instead of created_at)
            $sql = 'SELECT * FROM posts WHERE post_type = :type ORDER BY post_date DESC LIMIT ' . $limit;
            $stmt = $link->prepare($sql);
            $stmt->execute([':type' => $post_type]);
        } catch (Exception $e) {
            // column probably doesn't exist, fallback to simple posts query
                $sql = 'SELECT * FROM posts ORDER BY post_date DESC LIMIT ' . $limit;
            $stmt = $link->query($sql);
        }
        if ($stmt === false) {
            error_log('get_posts query failed: ' . implode(' | ', $link->errorInfo()));
            return [];
        }

        $posts = $stmt->fetchAll();

        // attach terms for each post if taxonomy tables exist
        try {
                $tstmt = $link->prepare('SELECT t.name, t.slug, tt.taxonomy FROM terms t JOIN term_taxonomy tt ON tt.term_id = t.term_id JOIN term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = :id');
                foreach ($posts as &$p) {
                    $pid = $p['ID'] ?? $p['id'] ?? null;
                    $tstmt->execute([':id' => $pid]);
                    $p['terms'] = $tstmt->fetchAll();
                }
        } catch (Exception $e) {
            // ignore, leave terms empty
            foreach ($posts as &$p) {
                $p['terms'] = [];
            }
        }

        return $posts;
    } catch (Exception $e) {
        return [];
    }
}

function get_posts_by_term(string $type, string $slug): array
{
    global $link;
    if (!isset($link)) {
        return [];
    }

    try {
            if ($type === 'category') {
                // order by post_date where available
                $stmt = $link->prepare('SELECT p.* FROM posts p JOIN post_categories pc ON pc.post_id = p.id JOIN categories c ON c.id = pc.category_id WHERE c.slug = :slug ORDER BY p.post_date DESC');
            $stmt->execute([':slug' => $slug]);
            return $stmt->fetchAll();
        }

        if ($type === 'author') {
                $stmt = $link->prepare('SELECT * FROM posts WHERE author_slug = :slug ORDER BY post_date DESC');
            $stmt->execute([':slug' => $slug]);
            return $stmt->fetchAll();
        }

        return [];
    } catch (Exception $e) {
        return [];
    }
}

function search_posts(string $query): array
{
    global $link;
    if (!isset($link) || $query === '') {
        return [];
    }

    try {
            $stmt = $link->prepare('SELECT * FROM posts WHERE title LIKE :q OR content LIKE :q ORDER BY post_date DESC');
        $like = '%' . $query . '%';
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// --- Rendering helpers to keep templates thin ---

if (!function_exists('esc')) {
    function esc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('render_404_section')) {
    function render_404_section(string $title = 'Pagina niet gevonden', string $message = 'De gevraagde pagina bestaat niet.'): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }
        echo '<section class="content"><div class="head"><h1>' . esc($title) . '</h1></div><div class="content"><div class="block"><p>' . esc($message) . '</p></div></div></section>';
    }
}

if (!function_exists('render_terms_links')) {
    function render_terms_links(array $terms = []): string
    {
        $out = [];
        foreach ($terms as $term) {
            $slug = esc($term['slug'] ?? '');
            $name = esc($term['name'] ?? '');
            $out[] = '<a class="term" href="' . BASE_URL . '/category/' . $slug . '">' . $name . '</a>';
        }
        return implode(' ', $out);
    }
}

if (!function_exists('render_post_item')) {
    function render_post_item(array $p): void
    {
        $title = esc($p['title'] ?? 'Untitled');
        $slug = esc($p['slug'] ?? '');
        $excerpt = esc(substr(strip_tags($p['content'] ?? ''), 0, 180));

        echo '<li class="post-item">';
        echo '<a class="post-title" href="' . BASE_URL . '/' . $slug . '">' . $title . '</a>';
        echo '<div class="excerpt">' . $excerpt . '</div>';
        if (!empty($p['terms']) && is_array($p['terms'])) {
            echo '<div class="terms">' . render_terms_links($p['terms']) . '</div>';
        }
        echo '</li>';
    }
}

if (!function_exists('render_posts_section')) {
    function render_posts_section(string $heading, array $posts = [], string $listClass = 'posts'): void
    {
        echo '<section class="content">';
        echo '<div class="head"><h1>' . esc($heading) . '</h1></div>';
        echo '<div class="content"><div class="block">';

        if (empty($posts)) {
            echo '<p>Geen berichten gevonden.</p>';
        } else {
            echo '<ul class="' . esc($listClass) . '">';
            foreach ($posts as $p) {
                render_post_item($p);
            }
            echo '</ul>';
        }

        echo '</div></div></section>';
    }
}

if (!function_exists('render_single_post')) {
    function render_single_post(array $post): void
    {
        if (empty($post)) {
            render_404_section('Bericht niet gevonden', 'Het gevraagde bericht bestaat niet.');
            return;
        }

        $title = esc($post['title'] ?? '');
        $content = $post['content'] ?? '';

        echo '<section class="content">';
        echo '<div class="head"><h1>' . $title . '</h1></div>';
        echo '<div class="content"><div class="block post">';
        echo '<div class="meta">';
        if (!empty($post['created_at'])) {
            echo '<time datetime="' . esc($post['created_at']) . '">' . esc($post['created_at']) . '</time>';
        }

        if (!empty($post['terms']) && is_array($post['terms'])) {
            echo '<div class="terms">' . render_terms_links($post['terms']) . '</div>';
        }

        if (function_exists('get_post_meta')) {
            $meta = get_post_meta((int)($post['id'] ?? 0));
            if (!empty($meta['_thumbnail_id'])) {
                echo '<div class="thumbnail">Thumbnail ID: ' . esc((string)$meta['_thumbnail_id']) . '</div>';
            }
        }

        echo '</div>'; // meta
        echo '<div class="content-body">' . $content . '</div>';
        echo '</div></div></section>';
    }
}

if (!function_exists('render_page')) {
    function render_page(array $page): void
    {
        if (empty($page)) {
            render_404_section('Pagina niet gevonden', 'De gevraagde pagina bestaat niet.');
            return;
        }

        $title = esc($page['title'] ?? '');
        $content = $page['content'] ?? '';

        echo '<section class="content">';
        echo '<div class="head"><h1>' . $title . '</h1></div>';
        echo '<div class="content"><div class="block page">' . $content;

        if (function_exists('get_post_meta') && !empty($page['id'])) {
            $meta = get_post_meta((int)$page['id']);
            if (!empty($meta)) {
                echo '<div class="page-meta">';
                foreach ($meta as $k => $vals) {
                    echo '<div class="meta-row"><strong>' . esc($k) . ':</strong> ' . esc(is_array($vals) ? implode(', ', $vals) : $vals) . '</div>';
                }
                echo '</div>';
            }
        }

        echo '</div></div></section>';
    }
}

// --- WP-style Loop API (lightweight) ---
if (!function_exists('setup_loop')) {
    function setup_loop(array $posts): void
    {
        $GLOBALS['wp_loop'] = [
            'posts' => array_values($posts),
            'index' => 0,
            'count' => count($posts),
        ];
        $GLOBALS['post'] = null;
    }
}

if (!function_exists('have_posts')) {
    function have_posts(): bool
    {
        $loop = $GLOBALS['wp_loop'] ?? null;
        if (!is_array($loop)) return false;
        return ($loop['index'] ?? 0) < ($loop['count'] ?? 0);
    }
}

if (!function_exists('the_post')) {
    function the_post(): void
    {
        $loop = &$GLOBALS['wp_loop'];
        if (!is_array($loop)) return;
        $i = $loop['index'] ?? 0;
        $post = $loop['posts'][$i] ?? null;
        $loop['index'] = $i + 1;
        setup_postdata($post);
    }
}

if (!function_exists('setup_postdata')) {
    function setup_postdata($post): void
    {
        if (is_array($post)) {
            // normalize fields for templates
            $post = array_change_key_case($post, CASE_LOWER);
            $p = [];
            $p['ID'] = $post['id'] ?? $post['ID'] ?? $post['id'] ?? null;
            $p['post_title'] = $post['post_title'] ?? $post['title'] ?? '';
            $p['post_content'] = $post['post_content'] ?? $post['content'] ?? '';
            $p['post_excerpt'] = $post['post_excerpt'] ?? '';
            $p['post_name'] = $post['post_name'] ?? $post['slug'] ?? '';
            $p['post_date'] = $post['post_date'] ?? $post['created_at'] ?? null;
            $p['terms'] = $post['terms'] ?? [];
            $GLOBALS['post'] = $p;
        } else {
            $GLOBALS['post'] = null;
        }
    }
}

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata(): void
    {
        $GLOBALS['post'] = null;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = null): string
    {
        $p = $post ?? $GLOBALS['post'] ?? null;
        if (is_array($p)) return esc($p['post_title'] ?? '');
        return '';
    }
}

if (!function_exists('the_title')) {
    function the_title($before = '', $after = ''): void
    {
        echo $before . get_the_title() . $after;
    }
}

if (!function_exists('get_the_content')) {
    function get_the_content($post = null): string
    {
        $p = $post ?? $GLOBALS['post'] ?? null;
        if (is_array($p)) return $p['post_content'] ?? '';
        return '';
    }
}

if (!function_exists('the_content')) {
    function the_content(): void
    {
        echo get_the_content();
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post = null, $words = 55): string
    {
        $p = $post ?? $GLOBALS['post'] ?? null;
        $text = '';
        if (is_array($p)) {
            $text = $p['post_excerpt'] ?: $p['post_content'] ?? '';
        }
        return wp_trim_words(strip_tags($text), $words);
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt(): void
    {
        echo esc(get_the_excerpt());
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = null): string
    {
        $p = $post ?? $GLOBALS['post'] ?? null;
        if (is_array($p)) {
            $slug = $p['post_name'] ?? '';
            return rtrim(BASE_URL, '/') . '/' . ltrim($slug, '/');
        }
        return BASE_URL . '/';
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num = 55, $more = '...') {
        $text = trim($text);
        if ($text === '') return '';
        $words = preg_split('/\s+/', $text);
        if (count($words) <= $num) return implode(' ', $words);
        return implode(' ', array_slice($words, 0, $num)) . $more;
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part(string $slug, ?string $name = null, array $vars = []): void
    {
        $dir = templates_dir();
        $candidates = [];
        if ($name) $candidates[] = $dir . $slug . '-' . $name . '.php';
        $candidates[] = $dir . $slug . '.php';
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                extract($vars, EXTR_SKIP);
                require $file;
                return;
            }
        }
    }
}


