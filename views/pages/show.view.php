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

$isAllowedLink = static function ($value) {
    $value = trim((string)$value);
    return $value !== '' && (bool)preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $value);
};

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

$builderBlocks = [];
if (!empty($pageData['builder_json'])) {
    $decodedBuilder = json_decode((string)$pageData['builder_json'], true);
    if (is_array($decodedBuilder)) {
        $builderBlocks = array_values($decodedBuilder);
    }
}

$heroPayload = (isset($templatePayload['hero']) && is_array($templatePayload['hero']))
    ? $templatePayload['hero']
    : [];

$builderHero = null;
foreach ($builderBlocks as $block) {
    if (!is_array($block)) {
        continue;
    }
    $type = strtolower(trim((string)($block['type'] ?? '')));
    if ($type !== 'hero') {
        continue;
    }
    $candidateData = isset($block['data']) && is_array($block['data']) ? $block['data'] : $block;
    $builderHero = $candidateData;
    break;
}

if (is_array($builderHero)) {
    if (trim((string)($builderHero['title'] ?? '')) !== '') {
        $heroPayload['title'] = (string)$builderHero['title'];
    }
    if (trim((string)($builderHero['subtitle'] ?? '')) !== '') {
        $heroPayload['subtitle'] = (string)$builderHero['subtitle'];
    }
    if (trim((string)($builderHero['cta_label'] ?? '')) !== '') {
        $heroPayload['cta_label'] = (string)$builderHero['cta_label'];
    }
    if (trim((string)($builderHero['cta_url'] ?? '')) !== '') {
        $heroPayload['cta_url'] = (string)$builderHero['cta_url'];
    }
}

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
if ($heroCtaLabel === '' || $heroCtaUrl === '' || !$isAllowedLink($heroCtaUrl)) {
    $heroCtaLabel = '';
    $heroCtaUrl = '';
}

$contentRaw = (string)($pageData['content'] ?? '');
$contentHtml = strip_tags($contentRaw, '<p><h1><h2><h3><h4><ul><ol><li><blockquote><strong><em><a><img><br>');
if ($contentHtml === trim($contentRaw)) {
    $contentHtml = nl2br(htmlspecialchars($contentRaw));
}

$contactFormState = [
    'status' => null,
    'message' => '',
    'errors' => [],
    'old' => [
        'name' => '',
        'email' => '',
        'phone' => '',
        'message' => '',
    ],
];

if (($_GET['contact'] ?? '') === 'ok') {
    $contactFormState['status'] = 'success';
    $contactFormState['message'] = 'Bedankt! Je bericht is ontvangen. We reageren zo snel mogelijk.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'page_contact_submit') {
    $postedCsrf = (string)($_POST['csrf'] ?? '');
    $csrfValid = false;
    if (isset($csrfTokenManager) && $csrfTokenManager instanceof \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface) {
        $csrfValid = $csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('fundamental_form', $postedCsrf));
    } elseif (isset($csrfToken) && is_string($csrfToken)) {
        $csrfValid = hash_equals($csrfToken, $postedCsrf);
    }

    if (!$csrfValid) {
        $contactFormState['status'] = 'error';
        $contactFormState['message'] = 'Ongeldige aanvraag. Probeer het opnieuw.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $emailTo = trim((string)($_POST['email_to'] ?? ''));

        $contactFormState['old'] = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        ];

        $errors = [];
        if ($name === '') {
            $errors[] = 'Naam is verplicht.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if ($message === '' || mb_strlen($message) < 10) {
            $errors[] = 'Bericht moet minimaal 10 tekens bevatten.';
        }
        if ($emailTo !== '' && !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
            $emailTo = '';
        }

        if (!empty($errors)) {
            $contactFormState['status'] = 'error';
            $contactFormState['message'] = 'Controleer het formulier en probeer opnieuw.';
            $contactFormState['errors'] = $errors;
        } else {
            $saved = false;
            if (isset($link) && $link instanceof PDO) {
                try {
                    $payload = json_encode([
                        'type' => 'contact_form_submission',
                        'page_slug' => (string)($pageData['slug'] ?? ''),
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'message' => $message,
                        'email_to' => $emailTo,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    if ($payload !== false && strlen($payload) > 1024) {
                        $payload = substr($payload, 0, 1024);
                    }

                    $stmt = $link->prepare(
                        'INSERT INTO logs (user_id, ip, user_agent, request_uri, referrer, method, created_at, payload, is_suspicious)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 0)'
                    );
                    $saved = $stmt->execute([
                        isset($session) && method_exists($session, 'get') ? $session->get('user_id') : null,
                        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                        (string)($_SERVER['REQUEST_URI'] ?? ''),
                        (string)($_SERVER['HTTP_REFERER'] ?? ''),
                        'POST',
                        $payload ?: null,
                    ]);
                } catch (Throwable $e) {
                    $saved = false;
                }
            }

            if ($saved) {
                header('Location: ' . $canonicalPath . '?contact=ok#contact-form');
                exit;
            }

            $contactFormState['status'] = 'error';
            $contactFormState['message'] = 'Je bericht kon niet worden opgeslagen. Probeer het later opnieuw.';
        }
    }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<main class="container py-4 site-main">
    <article class="card border-0 shadow-sm rounded-4 page-card">
        <section class="page-hero mb-4">
            <p class="eyebrow">Business Website Platform</p>
            <h1 class="display-6 fw-bold mb-2"><?php echo htmlspecialchars($heroTitle); ?></h1>
            <?php if ($heroSubtitle !== '') : ?>
                <p class="site-lead mb-0"><?php echo nl2br(htmlspecialchars($heroSubtitle)); ?></p>
            <?php endif; ?>
            <?php if ($heroCtaLabel !== '' && $heroCtaUrl !== '') : ?>
                <p class="site-actions mb-0"><a class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars($heroCtaUrl); ?>"><?php echo htmlspecialchars($heroCtaLabel); ?></a></p>
            <?php endif; ?>
        </section>

        <?php if (!empty($builderBlocks) || !empty($contentHtml)) : ?>
            <?php if (!empty($builderBlocks)) : ?>
                <div class="lh-lg page-content content-prose">
                    <?php foreach ($builderBlocks as $blockIndex => $block) : ?>
                        <?php
                            if (!is_array($block)) {
                                continue;
                            }
                            $blockType = strtolower(trim((string)($block['type'] ?? '')));
                            $blockData = isset($block['data']) && is_array($block['data']) ? $block['data'] : $block;
                            if ($blockType === 'hero') {
                                continue;
                            }
                        ?>

                        <?php if ($blockType === 'text') : ?>
                            <?php $textValue = trim((string)($blockData['content'] ?? '')); ?>
                            <?php if ($textValue !== '') : ?>
                                <div class="page-block fs-5"><?php echo nl2br(htmlspecialchars($textValue)); ?></div>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'image') : ?>
                            <?php
                                $src = trim((string)($blockData['src'] ?? ''));
                                $alt = trim((string)($blockData['alt'] ?? ''));
                                $caption = trim((string)($blockData['caption'] ?? ''));
                            ?>
                            <?php if ($isAllowedLink($src)) : ?>
                                <figure class="page-block figure w-100">
                                    <img class="img-fluid rounded-3 border" src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($alt); ?>">
                                    <?php if ($caption !== '') : ?><figcaption class="figure-caption mt-2"><?php echo htmlspecialchars($caption); ?></figcaption><?php endif; ?>
                                </figure>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'gallery') : ?>
                            <?php $images = isset($blockData['images']) && is_array($blockData['images']) ? $blockData['images'] : []; ?>
                            <?php if (!empty($images)) : ?>
                                <div class="page-block row g-3">
                                    <?php foreach ($images as $image) : ?>
                                        <?php
                                            $imgSrc = trim((string)($image['src'] ?? ''));
                                            $imgAlt = trim((string)($image['alt'] ?? ''));
                                        ?>
                                        <?php if ($isAllowedLink($imgSrc)) : ?>
                                            <div class="col-6 col-md-4">
                                                <img class="img-fluid rounded-3 border" src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($imgAlt); ?>">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'columns') : ?>
                            <?php $columns = isset($blockData['columns']) && is_array($blockData['columns']) ? $blockData['columns'] : []; ?>
                            <?php if (!empty($columns)) : ?>
                                <div class="page-block row g-3">
                                    <?php foreach ($columns as $column) : ?>
                                        <div class="col-12 col-md-6 col-lg">
                                            <section class="card h-100 bg-primary-subtle border-primary-subtle site-panel">
                                                <div class="card-body">
                                                    <?php $heading = trim((string)($column['heading'] ?? '')); ?>
                                                    <?php $body = trim((string)($column['content'] ?? '')); ?>
                                                    <?php if ($heading !== '') : ?><h3 class="h5"><?php echo htmlspecialchars($heading); ?></h3><?php endif; ?>
                                                    <?php if ($body !== '') : ?><p class="mb-0"><?php echo nl2br(htmlspecialchars($body)); ?></p><?php endif; ?>
                                                </div>
                                            </section>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'cta') : ?>
                            <?php
                                $ctaLabel = trim((string)($blockData['label'] ?? ''));
                                $ctaUrl = trim((string)($blockData['url'] ?? ''));
                                $ctaStyle = strtolower(trim((string)($blockData['style'] ?? 'primary')));
                                $ctaClass = $ctaStyle === 'secondary' ? 'btn-outline-primary' : 'btn-primary';
                            ?>
                            <?php if ($ctaLabel !== '' && $isAllowedLink($ctaUrl)) : ?>
                                <p class="page-block"><a class="btn <?php echo htmlspecialchars($ctaClass); ?> btn-lg" href="<?php echo htmlspecialchars($ctaUrl); ?>"><?php echo htmlspecialchars($ctaLabel); ?></a></p>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'quote') : ?>
                            <?php
                                $quote = trim((string)($blockData['quote'] ?? ''));
                                $author = trim((string)($blockData['author'] ?? ''));
                            ?>
                            <?php if ($quote !== '') : ?>
                                <blockquote class="page-block border-start border-4 border-primary ps-3 mb-0">
                                    <p class="mb-1"><?php echo htmlspecialchars($quote); ?></p>
                                    <?php if ($author !== '') : ?><footer class="text-secondary">- <?php echo htmlspecialchars($author); ?></footer><?php endif; ?>
                                </blockquote>
                            <?php endif; ?>
                        <?php elseif ($blockType === 'spacer') : ?>
                            <?php
                                $size = strtolower(trim((string)($blockData['size'] ?? 'md')));
                                $spacerMap = ['sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 5];
                                $spacerClass = 'my-3';
                                if (isset($spacerMap[$size])) {
                                    $spacerClass = 'my-' . $spacerMap[$size];
                                }
                            ?>
                            <div class="page-block <?php echo htmlspecialchars($spacerClass); ?>"></div>
                        <?php elseif ($blockType === 'contact_form') : ?>
                            <?php
                                $formTitle = trim((string)($blockData['title'] ?? 'Contactformulier'));
                                $emailTo = trim((string)($blockData['email_to'] ?? ''));
                            ?>
                            <section class="page-block card bg-light border site-panel" id="contact-form">
                                <div class="card-body">
                                    <h3 class="h5"><?php echo htmlspecialchars($formTitle); ?></h3>
                                    <p class="text-secondary mb-3">Vul je gegevens in. We nemen zo snel mogelijk contact met je op.</p>

                                    <?php if ($contactFormState['status'] === 'success') : ?>
                                        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($contactFormState['message']); ?></div>
                                    <?php elseif ($contactFormState['status'] === 'error' && $contactFormState['message'] !== '') : ?>
                                        <div class="alert alert-danger" role="alert">
                                            <div><?php echo htmlspecialchars($contactFormState['message']); ?></div>
                                            <?php if (!empty($contactFormState['errors'])) : ?>
                                                <ul class="mb-0 mt-2">
                                                    <?php foreach ($contactFormState['errors'] as $formError) : ?>
                                                        <li><?php echo htmlspecialchars((string)$formError); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="<?php echo htmlspecialchars($canonicalPath); ?>#contact-form">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)($csrfToken ?? '')); ?>">
                                        <input type="hidden" name="action" value="page_contact_submit">
                                        <input type="hidden" name="email_to" value="<?php echo htmlspecialchars($emailTo); ?>">

                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label" for="contact_name_<?php echo (int)$blockIndex; ?>">Naam</label>
                                                <input class="form-control" id="contact_name_<?php echo (int)$blockIndex; ?>" type="text" name="name" required value="<?php echo htmlspecialchars((string)($contactFormState['old']['name'] ?? '')); ?>">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label" for="contact_email_<?php echo (int)$blockIndex; ?>">E-mail</label>
                                                <input class="form-control" id="contact_email_<?php echo (int)$blockIndex; ?>" type="email" name="email" required value="<?php echo htmlspecialchars((string)($contactFormState['old']['email'] ?? '')); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="contact_phone_<?php echo (int)$blockIndex; ?>">Telefoon (optioneel)</label>
                                                <input class="form-control" id="contact_phone_<?php echo (int)$blockIndex; ?>" type="text" name="phone" value="<?php echo htmlspecialchars((string)($contactFormState['old']['phone'] ?? '')); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="contact_message_<?php echo (int)$blockIndex; ?>">Bericht</label>
                                                <textarea class="form-control" id="contact_message_<?php echo (int)$blockIndex; ?>" name="message" rows="5" required><?php echo htmlspecialchars((string)($contactFormState['old']['message'] ?? '')); ?></textarea>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                                                <button class="btn btn-primary" type="submit">Verstuur bericht</button>
                                                <?php if ($emailTo !== '') : ?>
                                                    <span class="small text-secondary">Ontvanger: <?php echo htmlspecialchars($emailTo); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </section>
                        <?php elseif ($blockType === 'map') : ?>
                            <?php
                                $mapTitle = trim((string)($blockData['title'] ?? 'Locatie'));
                                $embedUrl = trim((string)($blockData['embed_url'] ?? ''));
                            ?>
                            <?php if ($isAllowedLink($embedUrl)) : ?>
                                <section class="page-block card bg-light border site-panel">
                                    <div class="card-body">
                                        <h3 class="h5"><?php echo htmlspecialchars($mapTitle); ?></h3>
                                        <div class="ratio ratio-16x9 rounded-3 overflow-hidden border">
                                            <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="lh-lg page-content content-prose"><?php echo $contentHtml; ?></div>
            <?php endif; ?>
            <hr class="my-4 mx-4">
        <?php else : ?>
            <p class="text-secondary px-4 pb-4">Deze pagina heeft nog geen content.</p>
        <?php endif; ?>

        <?php if ($template === 'contact') : ?>
            <section class="page-block card bg-light border site-panel mx-4 mb-4">
                <div class="card-body">
                    <h3 class="h5">Direct contact</h3>
                    <p class="text-secondary mb-0">Bel ons op +31 (0)20 123 45 67 of mail naar info@example.com.</p>
                </div>
            </section>
        <?php endif; ?>
    </article>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>