<?php
$selectedTypeKey = (string)($contentSelectedTypeKey ?? 'services');
$selectedTypeDefinition = is_array($contentSelectedTypeDefinition ?? null) ? $contentSelectedTypeDefinition : [];

$contentIndexTitle = trim((string)($contentTypeIndexTitle ?? ''));
if ($contentIndexTitle === '') {
    $contentIndexTitle = (string)($selectedTypeDefinition['label'] ?? 'Content');
}

$contentIndexIntro = trim((string)($contentTypeIndexIntro ?? ''));
if ($contentIndexIntro === '') {
    $contentIndexIntro = (string)($selectedTypeDefinition['description'] ?? '');
}
?>
<style>
    .content-shell { display: grid; gap: 12px; }
    .content-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
    .content-head h3 { margin: 0; }
    .content-head p { margin: 4px 0 0; }
    .content-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid #dcdcde;
        border-radius: 999px;
        background: #f6f7f7;
        font-size: 12px;
        color: #3c434a;
        white-space: nowrap;
    }
    .content-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 10px; }
    .content-table .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .content-filter { display: grid; gap: 8px; grid-template-columns: minmax(0, 1fr) 180px auto auto; margin-bottom: 10px; }
    .content-meta { color: #646970; font-size: 12px; }
    @media (max-width: 980px) {
        .content-toolbar { flex-direction: column; align-items: stretch; }
        .content-filter { grid-template-columns: 1fr; }
        .content-head { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="content-shell">
    <div class="card">
        <div class="content-head">
            <div>
                <h3><?php echo htmlspecialchars($contentIndexTitle); ?></h3>
                <?php if ($contentIndexIntro !== '') : ?>
                    <p class="muted"><?php echo htmlspecialchars($contentIndexIntro); ?></p>
                <?php endif; ?>
            </div>
            <span class="content-type-badge">Type: <?php echo htmlspecialchars($selectedTypeDefinition['label'] ?? $selectedTypeKey); ?></span>
        </div>

        <div class="content-toolbar">
            <form method="GET" action="/dashboard/content/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>" class="content-filter">
                <input type="text" name="content_q" value="<?php echo htmlspecialchars($_GET['content_q'] ?? ''); ?>" placeholder="Zoeken op titel of slug">
                <select name="content_status">
                    <option value="">Alle statussen</option>
                    <option value="draft" <?php echo (($_GET['content_status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo (($_GET['content_status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                    <option value="archived" <?php echo (($_GET['content_status'] ?? '') === 'archived') ? 'selected' : ''; ?>>Archived</option>
                </select>
                <button type="submit" class="secondary">Filter</button>
                <a href="/dashboard/content/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>">Reset</a>
            </form>
            <?php if ($canPagesWrite) : ?>
                <a href="/dashboard/content/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>/create">+ Nieuw item</a>
            <?php endif; ?>
        </div>

        <table class="content-table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Publicatie</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contentManagedItems)) : ?>
                        <tr><td colspan="5" class="small">Nog geen items voor dit type.</td></tr>
                    <?php else : ?>
                        <?php foreach ($contentManagedItems as $managedItem) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($managedItem['title'] ?? ''); ?></strong>
                                    <div class="content-meta">ID <?php echo (int)($managedItem['id'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <a href="/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>/<?php echo htmlspecialchars($managedItem['slug'] ?? ''); ?>" target="_blank">
                                        /<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>/<?php echo htmlspecialchars($managedItem['slug'] ?? ''); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($managedItem['status'] ?? 'draft'); ?></td>
                                <td><?php echo !empty($managedItem['published_at']) ? htmlspecialchars((string)$managedItem['published_at']) : '-'; ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="/dashboard/content/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>/edit/<?php echo (int)$managedItem['id']; ?>">Bewerken</a>
                                        <form method="POST" action="/dashboard/content/<?php echo htmlspecialchars($selectedTypeDefinition['slug'] ?? $selectedTypeKey); ?>" onsubmit="return confirm('Dit item verwijderen?');">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="content_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$managedItem['id']; ?>">
                                            <input type="hidden" name="content_type" value="<?php echo htmlspecialchars($selectedTypeKey); ?>">
                                            <button type="submit" class="warn" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Verwijderen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <p class="small" style="margin-top:8px;">Totaal: <?php echo (int)($contentManagedTotal ?? 0); ?> · Pagina <?php echo (int)($contentManagedPage ?? 1); ?> van <?php echo (int)($contentManagedPagesTotal ?? 1); ?></p>
    </div>
</div>
