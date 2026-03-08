<?php
$title = trim((string)($contentItem['meta_title'] ?? '')) !== ''
    ? (string)$contentItem['meta_title']
    : (string)($contentItem['title'] ?? 'Content item');
$description = trim((string)($contentItem['meta_description'] ?? '')) !== ''
    ? (string)$contentItem['meta_description']
    : (string)($contentItem['excerpt'] ?? '');

$payload = [];
$rawPayload = (string)($contentItem['payload_json'] ?? '');
if ($rawPayload !== '') {
    $decodedPayload = json_decode($rawPayload, true);
    if (is_array($decodedPayload)) {
        $payload = $decodedPayload;
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

$renderRichText = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (class_exists('League\\CommonMark\\GithubFlavoredMarkdownConverter')) {
        try {
            $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $rendered = (string)$converter->convert($value)->getContent();
            if ($rendered !== '') {
                return $rendered;
            }
        } catch (Throwable $e) {
            // Fall back to plain escaped text when markdown conversion fails.
        }
    }

    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
};
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<main class="container py-4 site-main">
    <article class="card border-0 shadow-sm rounded-4 overflow-hidden article-shell">
        <div class="card-body p-4 p-lg-5 article-body">
        <p class="text-secondary mb-2"><?php echo htmlspecialchars($typeLabel); ?></p>
        <h1 class="h2"><?php echo htmlspecialchars((string)($contentItem['title'] ?? '')); ?></h1>
        <?php if (!empty($contentItem['excerpt'])) : ?>
            <p class="lead"><?php echo nl2br(htmlspecialchars((string)$contentItem['excerpt'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($contentItem['featured_image'])) : ?>
            <p><img class="img-fluid rounded-3" src="<?php echo htmlspecialchars((string)$contentItem['featured_image']); ?>" alt="<?php echo htmlspecialchars((string)($contentItem['title'] ?? '')); ?>"></p>
        <?php endif; ?>

        <?php if (!empty($contentItem['content'])) : ?>
            <div class="content-prose mb-4"><?php echo $renderRichText((string)$contentItem['content']); ?></div>
        <?php endif; ?>

        <?php if (!empty($payload)) : ?>
            <section class="row g-3">
                <?php foreach ($payload as $key => $value) : ?>
                    <?php if (trim((string)$value) === '') { continue; } ?>
                    <div class="col-12 col-md-6">
                        <div class="card bg-body-tertiary border-0 h-100 site-panel">
                            <div class="card-body">
                        <?php $fieldLabel = isset($payloadLabelMap[$key]) ? (string)$payloadLabelMap[$key] : (string)$key; ?>
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($fieldLabel); ?></strong>
                        <div class="text-secondary"><?php echo nl2br(htmlspecialchars((string)$value)); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
