<?php
$title = trim((string)($pageData['meta_title'] ?? '')) !== ''
    ? (string)$pageData['meta_title']
    : (string)($pageData['title'] ?? 'Pagina');
$description = trim((string)($pageData['meta_description'] ?? '')) !== ''
    ? (string)$pageData['meta_description']
    : (string)($pageData['excerpt'] ?? '');

$slug = trim((string)($pageData['slug'] ?? ''));
$canonicalPath = ($slug === '' || $slug === 'home') ? '/' : ('/' . $slug);
$canonical = BASE_URL . $canonicalPath;

$template = strtolower(trim((string)($pageData['template'] ?? 'default')));
if (!in_array($template, ['default', 'landing', 'contact'], true)) {
    $template = 'default';
}

$templatePayload = [];
if (!empty($pageData['template_payload_json'])) {
    $decodedPayload = json_decode((string)$pageData['template_payload_json'], true);
    if (is_array($decodedPayload)) {
        $templatePayload = $decodedPayload;
    }
}

$heroPayload = (isset($templatePayload['hero']) && is_array($templatePayload['hero']))
    ? $templatePayload['hero']
    : [];

$heroTitle = trim((string)($heroPayload['title'] ?? ''));
if ($heroTitle === '') {
    $heroTitle = (string)($pageData['title'] ?? 'Pagina');
}

$heroSubtitle = trim((string)($heroPayload['subtitle'] ?? ''));
if ($heroSubtitle === '') {
    $heroSubtitle = trim((string)($pageData['excerpt'] ?? ''));
}

$heroCtaLabel = trim((string)($heroPayload['cta_label'] ?? ''));
$heroCtaUrl = trim((string)($heroPayload['cta_url'] ?? ''));
if ($heroCtaLabel === '' || $heroCtaUrl === '' || !preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $heroCtaUrl)) {
    $heroCtaLabel = '';
    $heroCtaUrl = '';
}

$contentRaw = (string)($pageData['content'] ?? '');
$contentHtml = strip_tags($contentRaw, '<p><h1><h2><h3><h4><ul><ol><li><blockquote><strong><em><a><img><br>');
if ($contentHtml === trim($contentRaw)) {
    $contentHtml = nl2br(htmlspecialchars($contentRaw));
}

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
        :root {
            --pg-bg: #f2f5fb;
            --pg-text: #1d2a3a;
            --pg-muted: #5d6e83;
            --pg-card: #ffffff;
            --pg-border: #d7dfeb;
            --pg-shadow: 0 14px 36px rgba(28, 46, 76, 0.12);
            --pg-accent: #1f4a8a;
            --pg-accent-soft: #edf4ff;
        }
        body {
            font-family: "Segoe UI Variable Text", "Trebuchet MS", sans-serif;
            background: var(--pg-bg);
            margin: 0;
            color: var(--pg-text);
        }
        body.tpl-landing {
            background:
                radial-gradient(circle at 82% 7%, rgba(51, 102, 191, 0.16), transparent 32%),
                linear-gradient(160deg, #e9f2ff 0%, #f7f9fc 48%, #fbf7f1 100%);
        }
        body.tpl-contact {
            background:
                radial-gradient(circle at 15% 10%, rgba(31, 74, 138, 0.12), transparent 30%),
                #f7fafc;
        }
        .container {
            max-width: 980px;
            margin: 28px auto;
            padding: 0 16px;
        }
        .card {
            background: var(--pg-card);
            border: 1px solid var(--pg-border);
            border-radius: 14px;
            padding: 22px;
            box-shadow: var(--pg-shadow);
        }
        .card-soft {
            background: #f7fafc;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            padding: 16px;
        }
        h1, h2, h3 {
            font-family: "Segoe UI Variable Display", "Segoe UI Variable Text", "Trebuchet MS", sans-serif;
            letter-spacing: 0.01em;
            margin-top: 0;
        }
        .muted { color: var(--pg-muted); }
        .content {
            line-height: 1.75;
            font-size: 16px;
        }
        .content p:first-child { margin-top: 0; }
        .content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            border: 1px solid #d6e1ee;
        }
        .page-block { margin-top: 16px; }
        .hero {
            margin-bottom: 18px;
            padding: 22px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f5f9ff 0%, #eaf1ff 100%);
            border: 1px solid #cfe0fb;
        }
        .hero h1 {
            margin: 0 0 8px;
            font-size: clamp(1.55rem, 2.8vw, 2.15rem);
        }
        .hero .muted { margin: 0; }
        .hero-cta { margin-top: 14px; }
        .hero-cta a {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 9px;
            background: var(--pg-accent);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 8px 18px rgba(31, 74, 138, 0.22);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .hero-cta a:hover,
        .hero-cta a:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(31, 74, 138, 0.28);
        }
        @media (max-width: 640px) {
            .container {
                margin-top: 20px;
                padding: 0 12px;
            }
            .card { padding: 16px; }
            .hero {
                padding: 16px;
                margin-bottom: 14px;
            }
            .content { font-size: 15px; }
        }
    </style>
</head>
<body class="tpl-<?php echo htmlspecialchars($template); ?>">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<div class="container">
    <article class="card">
        <section class="hero">
            <h1><?php echo htmlspecialchars($heroTitle); ?></h1>
            <?php if ($heroSubtitle !== '') : ?>
                <p class="muted"><?php echo nl2br(htmlspecialchars($heroSubtitle)); ?></p>
            <?php endif; ?>
            <?php if ($heroCtaLabel !== '' && $heroCtaUrl !== '') : ?>
                <p class="hero-cta"><a href="<?php echo htmlspecialchars($heroCtaUrl); ?>"><?php echo htmlspecialchars($heroCtaLabel); ?></a></p>
            <?php endif; ?>
        </section>

        <?php if (!empty($contentHtml)) : ?>
            <div class="content"><?php echo $contentHtml; ?></div>
        <?php else : ?>
            <p class="muted">Deze pagina heeft nog geen content.</p>
        <?php endif; ?>

        <?php if ($template === 'contact') : ?>
            <section class="page-block card-soft">
                <h3>Direct contact</h3>
                <p class="muted">Bel ons op +31 (0)20 123 45 67 of mail naar info@example.com.</p>
            </section>
        <?php endif; ?>
    </article>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>