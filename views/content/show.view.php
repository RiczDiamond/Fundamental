<?php
$title = trim((string)($contentItem['meta_title'] ?? '')) !== ''
    ? (string)$contentItem['meta_title']
    : (string)($contentItem['title'] ?? 'Content item');
$description = trim((string)($contentItem['meta_description'] ?? '')) !== ''
    ? (string)$contentItem['meta_description']
    : (string)($contentItem['excerpt'] ?? '');

$payload = [];
if (!empty($contentItem['payload_json'])) {
    $decoded = json_decode((string)$contentItem['payload_json'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$typeLabel = trim((string)($contentTypeShowLabel ?? ''));
if ($typeLabel === '') {
    $typeLabel = (string)($contentTypeDefinition['label'] ?? ucfirst((string)$contentTypeKey));
}

$payloadLabelMap = isset($contentTypePayloadLabels) && is_array($contentTypePayloadLabels)
    ? $contentTypePayloadLabels
    : [];

$canonical = BASE_URL . trim((string)($contentTypeDefinition['slug'] ?? $contentTypeKey), '/') . '/' . trim((string)($contentItem['slug'] ?? ''));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <?php if ($description !== '') : ?>
        <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; color:#1f2937; }
        .container { max-width: 980px; margin: 24px auto; padding: 0 16px; }
        .card { background:#fff; border-radius:10px; padding:18px; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
        .muted { color:#64748b; }
        .content { line-height:1.65; }
        .content img { max-width:100%; height:auto; border-radius:8px; }
        .meta-grid { display:grid; gap:10px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); margin-top:12px; }
        .meta-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; }
    </style>
</head>
<body>
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<div class="container">
    <article class="card">
        <p class="muted" style="margin-top:0;"><?php echo htmlspecialchars($typeLabel); ?></p>
        <h1><?php echo htmlspecialchars((string)($contentItem['title'] ?? '')); ?></h1>
        <?php if (!empty($contentItem['excerpt'])) : ?>
            <p class="muted"><?php echo nl2br(htmlspecialchars((string)$contentItem['excerpt'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($contentItem['featured_image'])) : ?>
            <p><img src="<?php echo htmlspecialchars((string)$contentItem['featured_image']); ?>" alt="<?php echo htmlspecialchars((string)($contentItem['title'] ?? '')); ?>"></p>
        <?php endif; ?>

        <?php if (!empty($contentItem['content'])) : ?>
            <div class="content"><?php echo nl2br(htmlspecialchars((string)$contentItem['content'])); ?></div>
        <?php endif; ?>

        <?php if (!empty($payload)) : ?>
            <section class="meta-grid">
                <?php foreach ($payload as $key => $value) : ?>
                    <?php if (trim((string)$value) === '') { continue; } ?>
                    <div class="meta-item">
                        <?php $fieldLabel = isset($payloadLabelMap[$key]) ? (string)$payloadLabelMap[$key] : (string)$key; ?>
                        <strong><?php echo htmlspecialchars($fieldLabel); ?></strong>
                        <div><?php echo nl2br(htmlspecialchars((string)$value)); ?></div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </article>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
