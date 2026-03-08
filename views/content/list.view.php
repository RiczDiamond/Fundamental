<?php
$typeLabel = trim((string)($contentTypeListLabel ?? ''));
if ($typeLabel === '') {
    $typeLabel = (string)($contentTypeDefinition['label'] ?? ucfirst((string)($contentTypeKey ?? 'Content')));
}

$typeDescription = trim((string)($contentTypeListDescription ?? ''));
if ($typeDescription === '') {
    $typeDescription = (string)($contentTypeDefinition['description'] ?? '');
}
$slugPrefix = (string)($contentTypeDefinition['slug'] ?? (string)($contentTypeKey ?? 'content'));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($typeLabel); ?> - Fundamental CMS</title>
    <?php if ($typeDescription !== '') : ?>
        <meta name="description" content="<?php echo htmlspecialchars($typeDescription); ?>">
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fb; margin: 0; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
        .hero { background: #fff; border-radius: 10px; padding: 18px; border: 1px solid #dce1e8; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-top: 12px; }
        .card { background: #fff; border: 1px solid #dce1e8; border-radius: 10px; padding: 14px; }
        .card h3 { margin-top: 0; }
        .muted { color: #607086; }
        .thumb { width: 100%; max-height: 180px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<div class="wrap">
    <section class="hero">
        <h1 style="margin:0;"><?php echo htmlspecialchars($typeLabel); ?></h1>
        <?php if ($typeDescription !== '') : ?>
            <p class="muted" style="margin:8px 0 0;"><?php echo htmlspecialchars($typeDescription); ?></p>
        <?php endif; ?>
    </section>

    <section class="grid">
        <?php if (empty($contentItems)) : ?>
            <article class="card"><p class="muted">Nog geen gepubliceerde items.</p></article>
        <?php else : ?>
            <?php foreach ($contentItems as $item) : ?>
                <article class="card">
                    <?php if (!empty($item['featured_image'])) : ?>
                        <img class="thumb" src="<?php echo htmlspecialchars((string)$item['featured_image']); ?>" alt="<?php echo htmlspecialchars((string)($item['title'] ?? '')); ?>">
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars((string)($item['title'] ?? '')); ?></h3>
                    <?php if (!empty($item['excerpt'])) : ?>
                        <p class="muted"><?php echo nl2br(htmlspecialchars((string)$item['excerpt'])); ?></p>
                    <?php endif; ?>
                    <p><a href="/<?php echo htmlspecialchars($slugPrefix); ?>/<?php echo htmlspecialchars((string)($item['slug'] ?? '')); ?>">Lees meer</a></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
