<table class="content-table">
    <thead>
        <tr>
            <th>Titel</th>
            <th>Slug</th>
            <th>Status</th>
            <th>Published</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($contentManagedItems)) : ?>
            <tr><td colspan="5" class="small">No items found for this content type.</td></tr>
        <?php else : ?>
            <?php foreach ($contentManagedItems as $managedItem) : ?>
                <?php require __DIR__ . '/list-table-row.view.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
