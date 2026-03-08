<?php
$headerIsLoggedIn = isset($session) && is_object($session) && method_exists($session, 'has') && $session->has('user_id');
$headerTitle = $siteHeaderTitle ?? 'Fundamental CMS';

$headerMenuItems = [];
if (isset($link) && $link instanceof PDO) {
    try {
        $stmt = $link->query(
            "SELECT id, parent_id, label, url, sort_order
             FROM menu_items
             WHERE location = 'main' AND is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );
        $headerMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $headerMenuItems = [];
    }
}

if (empty($headerMenuItems)) {
    $headerMenuItems = [
        ['id' => 1, 'parent_id' => null, 'label' => 'Home', 'url' => '/', 'sort_order' => 10],
        ['id' => 2, 'parent_id' => null, 'label' => 'Blog', 'url' => '/blog', 'sort_order' => 20],
    ];
}

$menuNodesById = [];
foreach ($headerMenuItems as $item) {
    $item['children'] = [];
    $menuNodesById[(int)($item['id'] ?? 0)] = $item;
}

$headerMenuTree = [];
foreach ($menuNodesById as $id => $item) {
    $parentId = (int)($item['parent_id'] ?? 0);
    if ($parentId > 0 && isset($menuNodesById[$parentId])) {
        $menuNodesById[$parentId]['children'][] = $item;
    } else {
        $headerMenuTree[] = $item;
    }
}

$sortMenu = function (&$nodes) use (&$sortMenu) {
    usort($nodes, function ($a, $b) {
        $orderA = (int)($a['sort_order'] ?? 0);
        $orderB = (int)($b['sort_order'] ?? 0);
        if ($orderA === $orderB) {
            return ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
        }
        return $orderA <=> $orderB;
    });
    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            $sortMenu($node['children']);
        }
    }
};
$sortMenu($headerMenuTree);

$currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($currentPath) || $currentPath === '') {
    $currentPath = '/';
}

$normalizePath = static function (string $url): string {
    if (preg_match('/^(mailto:|tel:|#)/i', $url)) {
        return '';
    }

    $parsedPath = parse_url($url, PHP_URL_PATH);
    if (!is_string($parsedPath) || $parsedPath === '') {
        $parsedPath = '/';
    }

    $normalized = '/' . trim($parsedPath, '/');
    return $normalized === '//' ? '/' : (rtrim($normalized, '/') ?: '/');
};

$isActiveUrl = static function (string $url) use ($normalizePath, $currentPath): bool {
    $targetPath = $normalizePath($url);
    if ($targetPath === '') {
        return false;
    }

    $currentNormalized = $normalizePath($currentPath);
    return $currentNormalized === $targetPath
        || ($targetPath !== '/' && str_starts_with($currentNormalized . '/', rtrim($targetPath, '/') . '/'));
};

$renderMenuTree = function (array $nodes, bool $isSubmenu = false) use (&$renderMenuTree, $isActiveUrl) {
    if (empty($nodes)) {
        return;
    }

    echo '<ul class="fh-menu-tree ' . ($isSubmenu ? 'is-submenu' : 'is-root') . '">';
    foreach ($nodes as $node) {
        $url = (string)($node['url'] ?? '/');
        $label = (string)($node['label'] ?? 'Link');
        $hasChildren = !empty($node['children']);
        $itemClasses = [$hasChildren ? 'has-children' : 'no-children'];
        if ($isActiveUrl($url)) {
            $itemClasses[] = 'is-active';
        }

        echo '<li class="' . htmlspecialchars(implode(' ', $itemClasses)) . '"><a href="' . htmlspecialchars($url) . '"><span class="fh-menu-label">' . htmlspecialchars($label) . '</span>';
        if ($hasChildren) {
            echo '<span class="fh-menu-caret" aria-hidden="true"></span>';
        }
        echo '</a>';
        if (!empty($node['children'])) {
            $renderMenuTree($node['children'], true);
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<header class="fh-header">
    <div class="fh-inner">
        <a class="fh-brand" href="/" aria-label="Ga naar home">
            <span class="fh-brand-mark">F</span>
            <span class="fh-brand-copy">
                <span class="fh-brand-kicker">Editorial websites</span>
                <span class="fh-brand-name"><?php echo htmlspecialchars($headerTitle); ?></span>
            </span>
        </a>

        <nav class="fh-nav" aria-label="Hoofdnavigatie">
            <?php $renderMenuTree($headerMenuTree); ?>
            <span class="fh-auth-links">
                <?php if ($headerIsLoggedIn) : ?>
                    <a class="fh-auth-link fh-auth-link--primary" href="/dashboard/overview">Dashboard</a>
                    <a class="fh-auth-link" href="/logout">Logout</a>
                <?php else : ?>
                    <a class="fh-auth-link" href="/login">Login</a>
                    <a class="fh-auth-link fh-auth-link--primary" href="/register">Register</a>
                <?php endif; ?>
            </span>
        </nav>
    </div>
</header>
