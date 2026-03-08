<div class="content-pagination">
    <p class="small" style="margin:0;">Total: <?php echo (int)($contentManagedTotal ?? 0); ?> · Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></p>
    <div class="links">
        <?php if ($currentPage > 1) : ?>
            <a href="<?php echo $e($buildPageUrl($currentPage - 1)); ?>">Previous</a>
        <?php endif; ?>
        <?php if ($currentPage < $totalPages) : ?>
            <a href="<?php echo $e($buildPageUrl($currentPage + 1)); ?>">Next</a>
        <?php endif; ?>
    </div>
</div>
