<?php
$selectedTypeLabel = (string)($selectedTypeLabel ?? 'Content');
?>
<div class="content-head">
    <div>
        <h3><?php echo $e($contentIndexTitle); ?></h3>
        <?php if ($contentIndexIntro !== '') : ?>
            <p class="muted"><?php echo $e($contentIndexIntro); ?></p>
        <?php endif; ?>
    </div>
    <span class="content-type-badge">Type: <?php echo $e($selectedTypeLabel); ?></span>
</div>
