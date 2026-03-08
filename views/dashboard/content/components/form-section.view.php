<?php
$sectionTitle = (string)($sectionTitle ?? 'Section');
$sectionFields = is_array($sectionFields ?? null) ? $sectionFields : [];
$sectionGrid = !empty($sectionGrid);

if ($sectionFields === []) {
    return;
}
?>
<section class="section">
    <h3 style="margin:0 0 8px;"><?php echo htmlspecialchars($sectionTitle); ?></h3>
    <?php if ($sectionGrid) : ?>
        <div class="content-editor-grid">
            <?php foreach ($sectionFields as $crudField) : ?>
                <?php require __DIR__ . '/../../partials/crud-field.view.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <?php foreach ($sectionFields as $crudField) : ?>
            <?php require __DIR__ . '/../../partials/crud-field.view.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
