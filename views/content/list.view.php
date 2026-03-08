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
    <title><?php echo htmlspecialchars($typeLabel . ' - Fundamental CMS'); ?></title>
    <?php if ($typeDescription !== '') : ?>
        <meta name="description" content="<?php echo htmlspecialchars($typeDescription); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<main class="container py-4 site-main">
    <section class="site-intro mb-4">
        <p class="site-kicker">Content collectie</p>
        <h1 class="h2 mb-2"><?php echo htmlspecialchars($typeLabel); ?></h1>
        <?php if ($typeDescription !== '') : ?>
            <p class="site-lead mb-0"><?php echo htmlspecialchars($typeDescription); ?></p>
        <?php endif; ?>
    </section>

    <section class="row g-3">
        <?php if (empty($contentItems)) : ?>
            <div class="col-12">
                <article class="card border-0 shadow-sm rounded-4 site-panel">
                    <div class="card-body text-secondary">Nog geen gepubliceerde items.</div>
                </article>
            </div>
        <?php else : ?>
            <?php foreach ($contentItems as $item) : ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden site-panel">
                        <?php if (!empty($item['featured_image'])) : ?>
                            <img class="img-fluid" src="<?php echo htmlspecialchars((string)$item['featured_image']); ?>" alt="<?php echo htmlspecialchars((string)($item['title'] ?? '')); ?>">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column p-4">
                            <h3 class="h5"><?php echo htmlspecialchars((string)($item['title'] ?? '')); ?></h3>
                            <?php if (!empty($item['excerpt'])) : ?>
                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars((string)$item['excerpt'])); ?></p>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <a class="btn btn-outline-primary btn-sm" href="/<?php echo htmlspecialchars($slugPrefix); ?>/<?php echo htmlspecialchars((string)($item['slug'] ?? '')); ?>">Lees meer</a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
