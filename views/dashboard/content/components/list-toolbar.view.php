<div class="content-toolbar">
    <form method="GET" action="<?php echo $e($baseDashboardPath); ?>" class="content-filter">
        <input type="text" name="content_q" value="<?php echo $e($searchQuery); ?>" placeholder="Search by title or slug">
        <select name="content_status">
            <option value="">All statuses</option>
            <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="review" <?php echo $statusFilter === 'review' ? 'selected' : ''; ?>>In Review</option>
            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Published</option>
            <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>Archived</option>
        </select>
        <select name="content_per_page">
            <?php foreach ([10, 20, 30, 50] as $optionPerPage) : ?>
                <option value="<?php echo $optionPerPage; ?>" <?php echo $perPage === $optionPerPage ? 'selected' : ''; ?>>
                    <?php echo $optionPerPage; ?> per page
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="secondary">Apply</button>
        <a href="<?php echo $e($baseDashboardPath); ?>">Reset</a>
    </form>
    <?php if ($canPagesWrite) : ?>
        <a href="<?php echo $e($baseDashboardPath); ?>/create">+ Add New</a>
    <?php endif; ?>
</div>
