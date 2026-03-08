<style>
    .pages-shell {
        display: grid;
        gap: 12px;
    }
    .pages-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }
    .pages-topbar h2,
    .pages-section-title {
        margin: 0;
    }
    .pages-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .pages-intro {
        margin: 6px 0 0;
    }
    .pages-content-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 1fr);
    }
    .pages-filter-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 180px auto auto;
        gap: 8px;
        margin-top: 10px;
    }
    .pages-table td,
    .pages-table th {
        vertical-align: middle;
    }
    .pages-table .title-cell {
        min-width: 180px;
    }
    .pages-table .slug-cell {
        min-width: 150px;
    }
    .pages-table .actions-cell {
        min-width: 170px;
    }
    .pages-row-actions {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    .pages-summary {
        margin-top: 8px;
    }

    .menu-create-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 10px;
    }
    .menu-create-form .span-2 {
        grid-column: span 2;
    }
    .menu-checkbox {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #50575e;
    }
    .menu-toolbar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .menu-save-form {
        margin-top: 10px;
    }

    .menu-tree {
        list-style: none;
        margin: 10px 0 0;
        padding: 0;
    }
    .menu-tree .menu-tree {
        margin-top: 6px;
        padding-left: 22px;
    }
    .menu-node {
        border: 1px solid #dcdcde;
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 8px;
        background: #fff;
    }
    .menu-node.dragging {
        opacity: 0.55;
    }
    .menu-node.drop-top {
        border-top: 3px solid #2271b1;
    }
    .menu-node.drop-bottom {
        border-bottom: 3px solid #2271b1;
    }
    .menu-node.drop-inside {
        border-color: #2271b1;
        background: #e9f3fb;
    }
    .menu-node-head {
        display: flex;
        gap: 8px;
        justify-content: space-between;
        align-items: center;
        cursor: move;
    }
    .menu-node-title {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .menu-handle {
        font-size: 14px;
        color: #646970;
        cursor: move;
    }
    .menu-node-meta {
        font-size: 12px;
        color: #646970;
        word-break: break-all;
    }
    .menu-node-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .menu-delete-wrap {
        margin-top: 8px;
    }
    .menu-controls {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .menu-controls button {
        font-size: 12px;
        padding: 5px 8px;
    }
    .menu-collapsed > .menu-tree {
        display: none;
    }

    @media (max-width: 1100px) {
        .pages-content-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 900px) {
        .pages-filter-form {
            grid-template-columns: 1fr;
        }
        .menu-create-form {
            grid-template-columns: 1fr;
        }
        .menu-create-form .span-2 {
            grid-column: span 1;
        }
    }
    @media (max-width: 760px) {
        .pages-topbar {
            flex-direction: column;
            align-items: stretch;
        }
        .pages-actions {
            width: 100%;
        }
    }
</style>

<div class="pages-shell">
    <div class="card">
        <div class="pages-topbar">
            <div>
                <h2>Pagina's</h2>
                <p class="muted pages-intro">Beheer pagina's via aparte URL's voor aanmaken en bewerken.</p>
            </div>
            <div class="pages-actions">
                <?php if ($canPagesWrite) : ?>
                    <a href="/dashboard/pages/create">+ Nieuwe pagina</a>
                <?php endif; ?>
                <?php if ($canPagesWrite) : ?>
                    <span class="badge active">write toegestaan</span>
                <?php else : ?>
                    <span class="badge banned">alleen read</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="pages-content-grid">
        <div class="card">
            <h3 class="pages-section-title">Pagina-overzicht</h3>
            <form method="GET" action="/dashboard/pages" class="pages-filter-form">
                <input type="text" name="page_q" value="<?php echo htmlspecialchars($_GET['page_q'] ?? ''); ?>" placeholder="Zoeken op titel of slug">
                <select name="page_status">
                    <option value="">Alle statussen</option>
                    <option value="draft" <?php echo (($_GET['page_status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo (($_GET['page_status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                    <option value="archived" <?php echo (($_GET['page_status'] ?? '') === 'archived') ? 'selected' : ''; ?>>Archived</option>
                </select>
                <button type="submit" class="secondary">Filter</button>
                <a href="/dashboard/pages">Reset</a>
            </form>

            <table class="pages-table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Type</th>
                        <th>Template</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($managedPages)) : ?>
                        <tr><td colspan="6" class="small">Nog geen pagina's.</td></tr>
                    <?php else : ?>
                        <?php foreach ($managedPages as $managedPage) : ?>
                            <tr>
                                <td class="title-cell"><?php echo htmlspecialchars($managedPage['title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($managedPage['page_type'] ?? 'basic_page'); ?></td>
                                <td><?php echo htmlspecialchars($managedPage['template'] ?? 'default'); ?></td>
                                <td class="slug-cell">
                                    <a href="/<?php echo htmlspecialchars($managedPage['slug'] ?? ''); ?>" target="_blank">/<?php echo htmlspecialchars($managedPage['slug'] ?? ''); ?></a>
                                </td>
                                <td><?php echo htmlspecialchars($managedPage['status'] ?? 'draft'); ?></td>
                                <td class="actions-cell">
                                    <div class="pages-row-actions">
                                        <a href="/dashboard/pages/edit/<?php echo (int)$managedPage['id']; ?>">Bewerken</a>
                                        <form method="POST" action="/dashboard/pages" class="row" onsubmit="return confirm('Deze pagina verwijderen?');">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="page_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$managedPage['id']; ?>">
                                            <button type="submit" class="warn" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Verwijderen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="small pages-summary">Totaal: <?php echo (int)$managedPagesTotal; ?> · Pagina <?php echo (int)$pagePageNumber; ?> van <?php echo (int)$managedPagesPagesTotal; ?></p>
        </div>

        <div class="card">
            <h3 class="pages-section-title">Menu en navigatie</h3>
            <form method="POST" action="/dashboard/pages" class="menu-create-form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="menu_save">
                <input type="hidden" name="location" value="main">

                <input type="text" name="label" placeholder="Label" required>
                <input type="text" name="url" placeholder="URL, bijv. /blog" required>

                <select name="parent_id">
                    <option value="0">Top level</option>
                    <?php foreach ($menuItems as $menuItem) : ?>
                        <option value="<?php echo (int)$menuItem['id']; ?>"><?php echo htmlspecialchars($menuItem['label'] ?? 'Item'); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="menu-checkbox">
                    <input type="checkbox" id="menu-active" name="is_active" value="1" checked>
                    <label for="menu-active">Actief</label>
                </div>

                <button type="submit" class="secondary span-2" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Menu-item toevoegen</button>
            </form>

            <p class="small">Sleep items om volgorde of nesting aan te passen. Drop boven/onder voor sibling, in het item voor child.</p>

            <div class="menu-toolbar">
                <button type="button" class="secondary" id="menu-expand-all">Alles uitklappen</button>
                <button type="button" class="secondary" id="menu-collapse-all">Alles inklappen</button>
            </div>

            <ul class="menu-tree" id="menu-tree-root" data-location="main"></ul>

            <form method="POST" action="/dashboard/pages" id="menu-tree-save-form" class="menu-save-form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="menu_reorder_tree">
                <input type="hidden" name="menu_tree_json" id="menu-tree-json" value="[]">
                <button type="submit" class="secondary" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Volgorde opslaan</button>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var menuTreeData = <?php echo json_encode($menuTreeItems ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var menuRoot = document.getElementById('menu-tree-root');
        var menuSaveForm = document.getElementById('menu-tree-save-form');
        var menuTreeJsonField = document.getElementById('menu-tree-json');
        var menuExpandAllBtn = document.getElementById('menu-expand-all');
        var menuCollapseAllBtn = document.getElementById('menu-collapse-all');
        var dragNode = null;

        function renderTree(nodes, parentEl) {
            parentEl.innerHTML = '';
            (nodes || []).forEach(function (node) {
                var li = document.createElement('li');
                li.className = 'menu-node';
                li.draggable = true;
                li.dataset.id = String(node.id || 0);

                var head = document.createElement('div');
                head.className = 'menu-node-head';

                var left = document.createElement('div');
                left.className = 'menu-node-title';
                left.innerHTML = '<span class="menu-handle" title="Verslepen">::</span><div><strong>' + escapeHtml(node.label || 'Item') + '</strong><div class="menu-node-meta">' + escapeHtml(node.url || '/') + '</div></div>';

                var actions = document.createElement('div');
                actions.className = 'menu-node-actions';
                actions.innerHTML = '<span class="small">' + (node.is_active ? 'actief' : 'inactief') + '</span>' +
                    '<button type="button" class="secondary" data-toggle="collapse">In/uit</button>';

                head.appendChild(left);
                head.appendChild(actions);
                li.appendChild(head);

                var deleteWrap = document.createElement('div');
                deleteWrap.className = 'menu-delete-wrap';
                deleteWrap.innerHTML = '<form method="POST" action="/dashboard/pages" class="row"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="menu_delete"><input type="hidden" name="id" value="' + Number(node.id || 0) + '"><button type="submit" class="warn" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Verwijderen</button></form>';
                li.appendChild(deleteWrap);

                var childList = document.createElement('ul');
                childList.className = 'menu-tree';
                li.appendChild(childList);
                renderTree(node.children || [], childList);

                var controls = document.createElement('div');
                controls.className = 'menu-controls';
                controls.innerHTML = '' +
                    '<button type="button" class="secondary" data-action="up">Omhoog</button>' +
                    '<button type="button" class="secondary" data-action="down">Omlaag</button>' +
                    '<button type="button" class="secondary" data-action="indent">Inspringen</button>' +
                    '<button type="button" class="secondary" data-action="outdent">Uitspringen</button>';
                li.appendChild(controls);

                bindDragHandlers(li);
                bindActionHandlers(li);
                parentEl.appendChild(li);
            });
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function bindActionHandlers(nodeEl) {
            var toggleBtn = nodeEl.querySelector('[data-toggle="collapse"]');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    nodeEl.classList.toggle('menu-collapsed');
                });
            }

            nodeEl.querySelectorAll('[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = btn.getAttribute('data-action');
                    var current = nodeEl;
                    var parentList = current.parentElement;
                    if (!parentList) return;

                    if (action === 'up') {
                        var prev = current.previousElementSibling;
                        if (prev) {
                            parentList.insertBefore(current, prev);
                        }
                        return;
                    }

                    if (action === 'down') {
                        var next = current.nextElementSibling;
                        if (next) {
                            parentList.insertBefore(next, current);
                        }
                        return;
                    }

                    if (action === 'indent') {
                        var prevSibling = current.previousElementSibling;
                        if (!prevSibling) return;
                        var sub = prevSibling.querySelector(':scope > .menu-tree');
                        if (!sub) {
                            sub = document.createElement('ul');
                            sub.className = 'menu-tree';
                            prevSibling.appendChild(sub);
                        }
                        sub.appendChild(current);
                        prevSibling.classList.remove('menu-collapsed');
                        return;
                    }

                    if (action === 'outdent') {
                        var parentNode = parentList.closest('.menu-node');
                        if (!parentNode) return;
                        parentNode.parentElement.insertBefore(current, parentNode.nextElementSibling);
                    }
                });
            });
        }

        function clearDropState() {
            menuRoot.querySelectorAll('.menu-node').forEach(function (node) {
                node.classList.remove('drop-top', 'drop-bottom', 'drop-inside');
            });
        }

        function bindDragHandlers(nodeEl) {
            nodeEl.addEventListener('dragstart', function () {
                dragNode = nodeEl;
                nodeEl.classList.add('dragging');
            });

            nodeEl.addEventListener('dragend', function () {
                nodeEl.classList.remove('dragging');
                clearDropState();
                dragNode = null;
            });

            nodeEl.addEventListener('dragover', function (ev) {
                ev.preventDefault();
                if (!dragNode || dragNode === nodeEl) return;
                clearDropState();
                var rect = nodeEl.getBoundingClientRect();
                var y = ev.clientY - rect.top;
                if (y < rect.height * 0.25) {
                    nodeEl.classList.add('drop-top');
                } else if (y > rect.height * 0.75) {
                    nodeEl.classList.add('drop-bottom');
                } else {
                    nodeEl.classList.add('drop-inside');
                }
            });

            nodeEl.addEventListener('drop', function (ev) {
                ev.preventDefault();
                if (!dragNode || dragNode === nodeEl) return;

                var rect = nodeEl.getBoundingClientRect();
                var y = ev.clientY - rect.top;
                if (y < rect.height * 0.25) {
                    nodeEl.parentNode.insertBefore(dragNode, nodeEl);
                } else if (y > rect.height * 0.75) {
                    nodeEl.parentNode.insertBefore(dragNode, nodeEl.nextSibling);
                } else {
                    var childList = nodeEl.querySelector(':scope > .menu-tree');
                    if (!childList) {
                        childList = document.createElement('ul');
                        childList.className = 'menu-tree';
                        nodeEl.appendChild(childList);
                    }
                    childList.appendChild(dragNode);
                }

                clearDropState();
            });
        }

        function serializeMenu(ul) {
            var out = [];
            ul.querySelectorAll(':scope > .menu-node').forEach(function (nodeEl) {
                var childList = nodeEl.querySelector(':scope > .menu-tree');
                out.push({
                    id: Number(nodeEl.dataset.id || 0),
                    children: childList ? serializeMenu(childList) : []
                });
            });
            return out;
        }

        if (menuRoot) {
            renderTree(menuTreeData, menuRoot);
        }

        if (menuExpandAllBtn && menuRoot) {
            menuExpandAllBtn.addEventListener('click', function () {
                menuRoot.querySelectorAll('.menu-node').forEach(function (node) {
                    node.classList.remove('menu-collapsed');
                });
            });
        }

        if (menuCollapseAllBtn && menuRoot) {
            menuCollapseAllBtn.addEventListener('click', function () {
                menuRoot.querySelectorAll('.menu-node').forEach(function (node) {
                    if (node.querySelector(':scope > .menu-tree > .menu-node')) {
                        node.classList.add('menu-collapsed');
                    }
                });
            });
        }

        if (menuSaveForm && menuTreeJsonField) {
            menuSaveForm.addEventListener('submit', function () {
                menuTreeJsonField.value = JSON.stringify(serializeMenu(menuRoot));
            });
        }
    })();
</script>
