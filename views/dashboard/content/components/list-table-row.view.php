<?php
$itemId = (int)($managedItem['id'] ?? 0);
$itemSlug = (string)($managedItem['slug'] ?? '');
$itemStatus = strtolower(trim((string)($managedItem['status'] ?? 'draft')));
$statusLabel = $statusLabels[$itemStatus] ?? ucfirst($itemStatus);
?>
<tr>
    <td>
        <strong><?php echo $e($managedItem['title'] ?? ''); ?></strong>
        <div class="content-meta">ID <?php echo $itemId; ?></div>
    </td>
    <td>
        <a href="<?php echo $e($publicTypePath); ?>/<?php echo $e($itemSlug); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo $e($publicTypePath); ?>/<?php echo $e($itemSlug); ?>
        </a>
    </td>
    <td><span class="content-status is-<?php echo $e($itemStatus); ?>"><?php echo $e($statusLabel); ?></span></td>
    <td><?php echo $e($formatDate($managedItem['published_at'] ?? '')); ?></td>
    <td>
        <div class="actions">
            <a href="<?php echo $e($baseDashboardPath); ?>/edit/<?php echo $itemId; ?>">Edit</a>
            <form method="POST" action="<?php echo $e($baseDashboardPath); ?>" onsubmit="return confirm('Delete this item?');">
                <input type="hidden" name="csrf" value="<?php echo $e($csrfToken); ?>">
                <input type="hidden" name="action" value="content_delete">
                <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                <input type="hidden" name="content_type" value="<?php echo $e($selectedTypeKey); ?>">
                <button type="submit" class="warn" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Delete</button>
            </form>
        </div>
    </td>
</tr>
