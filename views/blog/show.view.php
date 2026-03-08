<?php
    $isPreviewMode = !empty($isPreviewMode);
    $title = trim((string)($post['meta_title'] ?? '')) !== '' ? $post['meta_title'] : ($post['title'] ?? 'Blog');
    $description = trim((string)($post['meta_description'] ?? '')) !== ''
        ? $post['meta_description']
        : (trim((string)($post['intro'] ?? '')) !== '' ? $post['intro'] : ($post['excerpt'] ?? 'Artikel op onze blog'));
    $canonical = BASE_URL . ($post['permalink'] ?? '/blog');
    $ogImage = trim((string)($post['og_image'] ?? '')) !== '' ? $post['og_image'] : ($post['featured_image'] ?? '');
    $authorLabel = $post['author_display_name'] ?? $post['author_name'] ?? 'Onbekend';
    $updatedDate = $post['updated_at'] ?? null;
    $publishedDate = $post['published_at'] ?? $post['created_at'] ?? null;

    $contentRaw = (string)($post['content'] ?? '');
    $contentDecoded = html_entity_decode($contentRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (class_exists('App\\Services\\SanitizerService')) {
        $contentSanitizer = new App\Services\SanitizerService();
        $contentHtml = (string)$contentSanitizer->sanitizeHtml($contentDecoded);
    } else {
        $contentHtml = strip_tags($contentDecoded, '<p><h2><h3><h4><ul><ol><li><blockquote><strong><em><a><img><br>');
    }

    // If nothing resembles HTML after sanitization, render as plain text with line breaks.
    if (!preg_match('/<\/?[a-z][^>]*>/i', $contentHtml)) {
        if (class_exists('League\\CommonMark\\GithubFlavoredMarkdownConverter')) {
            try {
                $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
                $contentHtml = (string)$converter->convert($contentDecoded)->getContent();
            } catch (Throwable $e) {
                $contentHtml = nl2br(htmlspecialchars($contentDecoded, ENT_QUOTES, 'UTF-8'));
            }
        } else {
            $contentHtml = nl2br(htmlspecialchars($contentDecoded, ENT_QUOTES, 'UTF-8'));
        }
    }

    $toc = [];
    if (preg_match_all('/<(h2|h3)>(.*?)<\/\1>/i', $contentHtml, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $idx => $m) {
            $text = trim(strip_tags($m[2]));
            if ($text === '') {
                continue;
            }
            $anchor = 'sec-' . ($idx + 1) . '-' . preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($text));
            $contentHtml = preg_replace('/' . preg_quote($m[0], '/') . '/', '<' . strtolower($m[1]) . ' id="' . $anchor . '">' . $m[2] . '</' . strtolower($m[1]) . '>', $contentHtml, 1);
            $toc[] = ['level' => strtolower($m[1]), 'text' => $text, 'anchor' => $anchor];
        }
    }

    $tagItems = array_values(array_filter(array_map('trim', explode(',', (string)($post['tags'] ?? '')))));

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
    <?php if (!empty($ogImage)) : ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>
    <script type="application/ld+json">
        <?php echo json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'] ?? '',
            'datePublished' => $publishedDate,
            'dateModified' => $updatedDate ?: $publishedDate,
            'author' => ['@type' => 'Person', 'name' => $authorLabel],
            'mainEntityOfPage' => $canonical,
            'description' => $description,
            'image' => !empty($ogImage) ? [$ogImage] : null,
            'keywords' => implode(', ', $tagItems),
            'articleSection' => $post['category'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<main class="container py-4 site-main">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/blog">Blog</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($post['title'] ?? ''); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <article class="col-12 col-xl-8" id="article-content">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden article-shell">
                <div class="card-body p-4 p-lg-5 article-body">
            <?php if ($isPreviewMode) : ?>
                <div class="alert alert-warning">
                    Preview-modus <?php if (!empty($post['required_role'])) : ?>· Alleen rol: <?php echo htmlspecialchars($post['required_role']); ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <h1 class="h2"><?php echo htmlspecialchars($post['title'] ?? ''); ?></h1>
            <div class="d-flex flex-wrap gap-3 small text-secondary mb-3">
                <span>Auteur: <?php echo htmlspecialchars($authorLabel); ?></span>
                <span>Gepubliceerd: <?php echo htmlspecialchars($publishedDate ?? '-'); ?></span>
                <?php if (!empty($updatedDate) && $updatedDate !== $publishedDate) : ?><span>Updated: <?php echo htmlspecialchars($updatedDate); ?></span><?php endif; ?>
                <span><?php echo (int)$readMinutes; ?> min lezen</span>
                <span><?php echo (int)($post['view_count'] ?? 0); ?> views</span>
            </div>

            <?php if (!empty($post['featured_image'])) : ?>
                <img class="img-fluid rounded-3 mb-4" src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title'] ?? 'Featured image'); ?>" loading="lazy">
            <?php endif; ?>

            <?php if (!empty($post['intro'])) : ?>
                <p class="lead"><?php echo nl2br(htmlspecialchars($post['intro'])); ?></p>
            <?php elseif (!empty($post['excerpt'])) : ?>
                <p class="lead"><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($post['category'])) : ?>
                <div class="mb-2"><span class="text-secondary">Categorie:</span> <a href="/blog?category=<?php echo urlencode($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></a></div>
            <?php endif; ?>

            <?php if (!empty($tagItems)) : ?>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <?php foreach ($tagItems as $tag) : ?>
                        <span class="badge text-bg-light border">#<?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="content-prose mb-4"><?php echo $contentHtml; ?></div>

            <section id="engagement" class="card bg-light border-0 mb-4 site-panel">
                <div class="card-body">
                <h3 class="h5">Vond je dit artikel nuttig?</h3>
                <?php if ($isPreviewMode) : ?>
                    <p class="text-secondary mb-0">Likes zijn uitgeschakeld tijdens preview.</p>
                <?php else : ?>
                    <form method="POST" action="<?php echo htmlspecialchars($post['permalink'] ?? '/blog'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="blog_like">
                        <div class="d-flex gap-3 align-items-center">
                            <button class="btn btn-primary" type="submit" <?php echo !empty($likedByCurrent) ? 'disabled' : ''; ?>><?php echo !empty($likedByCurrent) ? 'Geliked' : 'Like / Clap'; ?></button>
                            <span class="text-secondary"><?php echo (int)($post['like_count'] ?? 0); ?> likes</span>
                        </div>
                    </form>
                <?php endif; ?>
                </div>
            </section>

            <section id="comments" class="card border-0 bg-body-tertiary mb-4 site-panel">
                <div class="card-body">
                <h3 class="h5">Reacties</h3>
                <?php if ($isPreviewMode) : ?>
                    <p class="text-secondary">Reacties zijn uitgeschakeld tijdens preview.</p>
                <?php elseif (empty($comments)) : ?>
                    <p class="text-secondary">Nog geen reacties.</p>
                <?php else : ?>
                    <?php foreach ($comments as $comment) : ?>
                        <div class="border rounded-3 p-3 mb-3 bg-white">
                            <strong><?php echo htmlspecialchars($comment['author_name'] ?? 'Bezoeker'); ?></strong>
                            <div class="small text-secondary mb-2"><?php echo htmlspecialchars($comment['created_at'] ?? ''); ?></div>
                            <div><?php echo nl2br(htmlspecialchars($comment['comment'] ?? '')); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$isPreviewMode) : ?>
                    <h4 class="h6 mt-4">Plaats een reactie</h4>
                    <form method="POST" action="<?php echo htmlspecialchars($post['permalink'] ?? '/blog'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="blog_comment">
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-md-6">
                                <input class="form-control" type="text" name="author_name" placeholder="Naam" <?php echo $session->has('user_id') ? 'value="Ingelogde gebruiker" readonly' : ''; ?> required>
                            </div>
                            <div class="col-12 col-md-6">
                                <input class="form-control" type="email" name="author_email" placeholder="Email (optioneel)">
                            </div>
                        </div>
                        <textarea class="form-control mb-2" name="comment" rows="4" placeholder="Je reactie" required></textarea>
                        <button type="submit" class="btn btn-dark">Reageer</button>
                    </form>
                <?php endif; ?>
                </div>
            </section>

            <section class="card border-0 shadow-sm mb-4 site-panel">
                <div class="card-body">
                <h3 class="h5">Navigatie</h3>
                <div class="d-grid gap-2">
                    <?php if (!empty($previousPost)) : ?><a href="<?php echo htmlspecialchars($previousPost['permalink']); ?>">← Vorig artikel: <?php echo htmlspecialchars($previousPost['title']); ?></a><?php endif; ?>
                    <?php if (!empty($nextPost)) : ?><a href="<?php echo htmlspecialchars($nextPost['permalink']); ?>">Volgend artikel: <?php echo htmlspecialchars($nextPost['title']); ?> →</a><?php endif; ?>
                </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm site-panel">
                <div class="card-body">
                <h3 class="h5">Gerelateerde artikelen</h3>
                <?php if (empty($relatedPosts)) : ?>
                    <p class="text-secondary mb-0">Geen gerelateerde artikelen gevonden.</p>
                <?php else : ?>
                    <ul class="mb-0">
                        <?php foreach ($relatedPosts as $rel) : ?>
                            <li><a href="<?php echo htmlspecialchars($rel['permalink']); ?>"><?php echo htmlspecialchars($rel['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>
                </div>
            </div>
        </article>

        <aside class="col-12 col-xl-4">
            <?php if (!empty($toc)) : ?>
                <section class="card border-0 shadow-sm rounded-4 mb-3 site-panel toc-panel">
                    <div class="card-body">
                    <h3 class="h6">Inhoudsopgave</h3>
                    <?php foreach ($toc as $item) : ?>
                        <a class="d-block small mb-1" href="#<?php echo htmlspecialchars($item['anchor']); ?>"><?php echo htmlspecialchars($item['text']); ?></a>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="card border-0 shadow-sm rounded-4 mb-3 site-panel">
                <div class="card-body">
                <h3 class="h6">Deel dit artikel</h3>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($canonical); ?>">LinkedIn</a>
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonical); ?>">Facebook</a>
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="https://x.com/intent/post?url=<?php echo urlencode($canonical); ?>&text=<?php echo urlencode($post['title'] ?? ''); ?>">X</a>
                </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-4 mb-3 site-panel">
                <div class="card-body">
                <h3 class="h6">Categorieën</h3>
                <?php if (empty($categories)) : ?>
                    <p class="text-secondary mb-0">Nog geen categorieën.</p>
                <?php else : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($categories as $cat) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                <span class="badge text-bg-secondary"><?php echo (int)$cat['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-4 mb-3 site-panel">
                <div class="card-body">
                <h3 class="h6">Nieuwsbrief</h3>
                <p class="text-secondary">Blijf op de hoogte van nieuwe artikelen.</p>
                <form>
                    <input class="form-control mb-2" type="email" placeholder="Email">
                    <button class="btn btn-dark w-100" type="button">Inschrijven</button>
                </form>
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-4 site-panel">
                <div class="card-body">
                <h3 class="h6">Auteur</h3>
                <p><strong><?php echo htmlspecialchars($authorLabel); ?></strong></p>
                <p class="text-secondary">Volg voor meer updates en artikelen.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="/contact">Contact</a>
                    <a class="btn btn-outline-secondary btn-sm" href="/blog">Meer posts</a>
                </div>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
