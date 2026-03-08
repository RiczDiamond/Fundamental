<div class="row">
    <button type="submit" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>><?php echo $isEdit ? 'Update' : 'Publish'; ?></button>
    <a href="<?php echo htmlspecialchars($baseDashboardPath); ?>">Cancel</a>
</div>
