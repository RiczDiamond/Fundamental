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

$renderMenuTree = function (array $nodes, bool $isSubmenu = false) use (&$renderMenuTree) {
    if (empty($nodes)) {
        return;
    }

    echo '<ul class="fh-menu-tree ' . ($isSubmenu ? 'is-submenu' : 'is-root') . '">';
    foreach ($nodes as $node) {
        $url = (string)($node['url'] ?? '/');
        $label = (string)($node['label'] ?? 'Link');
        $hasChildren = !empty($node['children']);
        echo '<li class="' . ($hasChildren ? 'has-children' : 'no-children') . '"><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
        if (!empty($node['children'])) {
            $renderMenuTree($node['children'], true);
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<style>
    .fh-header {
        background: linear-gradient(110deg, #0b1325 0%, #162845 60%, #1b365f 100%);
        color: #e5edf8;
        padding: 14px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 28px rgba(10, 18, 36, 0.2);
    }
    .fh-header .fh-inner {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
    }
    .fh-brand {
        font-family: "Segoe UI Variable Text", "Trebuchet MS", sans-serif;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: 0.03em;
        color: #f8fbff;
        text-decoration: none;
        white-space: nowrap;
    }
    .fh-nav {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px 14px;
    }
    .fh-nav a {
        color: #d2dfef;
        text-decoration: none;
        font-size: 14px;
        line-height: 1.2;
        transition: color .15s ease;
    }
    .fh-nav a:hover,
    .fh-nav a:focus-visible { color: #ffffff; }
    .fh-menu-tree {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .fh-menu-tree.is-root {
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .fh-menu-tree li { position: relative; }
    .fh-menu-tree li > a {
        display: inline-flex;
        align-items: center;
        padding: 8px 10px;
        border-radius: 9px;
    }
    .fh-menu-tree li > a:hover,
    .fh-menu-tree li > a:focus-visible {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
    .fh-menu-tree li > .fh-menu-tree.is-submenu {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        min-width: 210px;
        background: #0f1f39;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        padding: 8px;
        z-index: 30;
        box-shadow: 0 12px 28px rgba(8, 13, 24, 0.28);
    }
    .fh-menu-tree li:hover > .fh-menu-tree.is-submenu,
    .fh-menu-tree li:focus-within > .fh-menu-tree.is-submenu {
        display: block;
    }
    .fh-menu-tree.is-submenu li > a {
        display: block;
        width: 100%;
        padding: 8px 10px;
        border-radius: 8px;
    }
    .fh-auth-links {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding-left: 8px;
        margin-left: 6px;
        border-left: 1px solid rgba(255, 255, 255, 0.22);
    }
    .fh-auth-links a {
        padding: 7px 10px;
        border-radius: 8px;
    }
    .fh-auth-links a:hover,
    .fh-auth-links a:focus-visible {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
    @media (max-width: 900px) {
        .fh-header .fh-inner {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .fh-nav { justify-content: flex-start; }
        .fh-menu-tree.is-root {
            flex-wrap: wrap;
            row-gap: 6px;
        }
    }
    @media (max-width: 680px) {
        .fh-menu-tree.is-root {
            flex-direction: column;
            align-items: stretch;
        }
        .fh-menu-tree li > .fh-menu-tree.is-submenu {
            display: block;
            position: static;
            min-width: 0;
            margin: 4px 0 0 10px;
            border-radius: 8px;
            box-shadow: none;
        }
        .fh-auth-links {
            margin-left: 0;
            padding-left: 0;
            border-left: none;
        }
    }
</style>
<header class="fh-header">
    <div class="fh-inner">
        <a class="fh-brand" href="/"><?php echo htmlspecialchars($headerTitle); ?></a>
        <nav class="fh-nav">
            <?php $renderMenuTree($headerMenuTree); ?>
            <span class="fh-auth-links">
                <?php if ($headerIsLoggedIn) : ?>
                    <a href="/dashboard/overview">Dashboard</a>
                    <a href="/logout">Logout</a>
                <?php else : ?>
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                <?php endif; ?>
            </span>
        </nav>
    </div>
</header>
