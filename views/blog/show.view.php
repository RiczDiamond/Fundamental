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
    $contentHtml = strip_tags($contentRaw, '<p><h2><h3><h4><ul><ol><li><blockquote><strong><em><a><img><br>');
    if ($contentHtml === trim($contentRaw)) {
        $contentHtml = nl2br(htmlspecialchars($contentRaw));
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
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; color:#1f2937; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .layout { display:grid; grid-template-columns: 1fr 300px; gap:16px; }
        .card { background:#fff; border-radius:10px; padding:20px; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
        a { color:#2563eb; text-decoration:none; }
        .muted { color:#64748b; font-size:13px; }
        .meta { display:flex; gap:10px; flex-wrap:wrap; margin:8px 0 16px 0; color:#64748b; font-size:13px; }
        .breadcrumb { font-size:13px; margin-bottom:10px; color:#64748b; }
        .featured { width:100%; border-radius:8px; margin:10px 0 14px 0; }
        .article-block { margin-top:14px; }
        .tags { display:flex; gap:6px; flex-wrap:wrap; margin:10px 0; }
        .tag { background:#e2e8f0; color:#334155; border-radius:999px; padding:3px 9px; font-size:12px; }
        .toc a { display:block; padding:4px 0; }
        .toc .h3 { margin-left:10px; font-size:13px; }
        .share a { display:inline-block; margin-right:8px; margin-bottom:6px; font-size:13px; }
        .progress-wrap { position: sticky; top: 0; z-index: 10; background:#f5f7fb; }
        .progress { height:4px; background:#dbeafe; border-radius:999px; overflow:hidden; }
        .progress > i { display:block; height:100%; width:0%; background:#2563eb; }
        .cta { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-top:14px; }
        .comment { border-top:1px solid #e2e8f0; padding:10px 0; }
        .comment:first-child { border-top:none; }
        input, textarea, button { padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px; font-size:14px; }
        textarea { width:100%; min-height:90px; }
        button { background:#2563eb; color:#fff; border:none; cursor:pointer; }
        .row { display:flex; gap:8px; flex-wrap:wrap; }
        .aside-sticky { position: sticky; top: 18px; }
        @media (max-width: 960px) { .layout { grid-template-columns: 1fr; } .aside-sticky { position: static; } }
    </style>
</head>
<body>
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<div class="container">
    <div class="progress-wrap"><div class="progress"><i id="read-progress"></i></div></div>
    <div class="breadcrumb"><a href="/">Home</a> → <a href="/blog">Blog</a> → <?php echo htmlspecialchars($post['title'] ?? ''); ?></div>
    <div class="layout">
        <article class="card" id="article-content">
            <?php if ($isPreviewMode) : ?>
                <div class="alert-ok" style="margin-bottom:10px; padding:10px 12px; border-radius:8px; background:#dbeafe; color:#1e3a8a;">
                    Preview-modus <?php if (!empty($post['required_role'])) : ?>· Alleen rol: <?php echo htmlspecialchars($post['required_role']); ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($post['title'] ?? ''); ?></h1>
            <div class="meta">
                <span>Auteur: <?php echo htmlspecialchars($authorLabel); ?></span>
                <span>Gepubliceerd: <?php echo htmlspecialchars($publishedDate ?? '-'); ?></span>
                <?php if (!empty($updatedDate) && $updatedDate !== $publishedDate) : ?><span>Updated: <?php echo htmlspecialchars($updatedDate); ?></span><?php endif; ?>
                <span><?php echo (int)$readMinutes; ?> min lezen</span>
                <span><?php echo (int)($post['view_count'] ?? 0); ?> views</span>
            </div>

            <?php if (!empty($post['featured_image'])) : ?>
                <img class="featured" src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title'] ?? 'Featured image'); ?>" loading="lazy">
            <?php endif; ?>

            <?php if (!empty($post['intro'])) : ?>
                <p><strong><?php echo nl2br(htmlspecialchars($post['intro'])); ?></strong></p>
            <?php elseif (!empty($post['excerpt'])) : ?>
                <p><strong><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></strong></p>
            <?php endif; ?>

            <?php if (!empty($post['category'])) : ?>
                <div class="muted">Categorie: <a href="/blog?category=<?php echo urlencode($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></a></div>
            <?php endif; ?>

            <?php if (!empty($tagItems)) : ?>
                <div class="tags">
                    <?php foreach ($tagItems as $tag) : ?>
                        <span class="tag">#<?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div><?php echo $contentHtml; ?></div>

            <section id="engagement" class="cta">
                <h3>Vond je dit artikel nuttig?</h3>
                <?php if ($isPreviewMode) : ?>
                    <p class="muted">Likes zijn uitgeschakeld tijdens preview.</p>
                <?php else : ?>
                    <form method="POST" action="<?php echo htmlspecialchars($post['permalink'] ?? '/blog'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="blog_like">
                        <button type="submit" <?php echo !empty($likedByCurrent) ? 'disabled' : ''; ?>><?php echo !empty($likedByCurrent) ? 'Geliked' : 'Like / Clap'; ?></button>
                        <span class="muted" style="margin-left:8px;"><?php echo (int)($post['like_count'] ?? 0); ?> likes</span>
                    </form>
                <?php endif; ?>
            </section>

            <section id="comments" class="card" style="margin-top:14px;">
                <h3>Reacties</h3>
                <?php if ($isPreviewMode) : ?>
                    <p class="muted">Reacties zijn uitgeschakeld tijdens preview.</p>
                <?php elseif (empty($comments)) : ?>
                    <p class="muted">Nog geen reacties.</p>
                <?php else : ?>
                    <?php foreach ($comments as $comment) : ?>
                        <div class="comment">
                            <strong><?php echo htmlspecialchars($comment['author_name'] ?? 'Bezoeker'); ?></strong>
                            <div class="muted"><?php echo htmlspecialchars($comment['created_at'] ?? ''); ?></div>
                            <div><?php echo nl2br(htmlspecialchars($comment['comment'] ?? '')); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$isPreviewMode) : ?>
                    <h4 style="margin-top:12px;">Plaats een reactie</h4>
                    <form method="POST" action="<?php echo htmlspecialchars($post['permalink'] ?? '/blog'); ?>">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="blog_comment">
                        <div class="row" style="margin-bottom:8px;">
                            <input type="text" name="author_name" placeholder="Naam" <?php echo $session->has('user_id') ? 'value="Ingelogde gebruiker" readonly' : ''; ?> required>
                            <input type="email" name="author_email" placeholder="Email (optioneel)">
                        </div>
                        <textarea name="comment" placeholder="Je reactie" required></textarea>
                        <button type="submit" style="margin-top:8px;">Reageer</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="card" style="margin-top:14px;">
                <h3>Navigatie</h3>
                <div class="row">
                    <?php if (!empty($previousPost)) : ?><a href="<?php echo htmlspecialchars($previousPost['permalink']); ?>">← Vorig artikel: <?php echo htmlspecialchars($previousPost['title']); ?></a><?php endif; ?>
                    <?php if (!empty($nextPost)) : ?><a href="<?php echo htmlspecialchars($nextPost['permalink']); ?>">Volgend artikel: <?php echo htmlspecialchars($nextPost['title']); ?> →</a><?php endif; ?>
                </div>
            </section>

            <section class="card" style="margin-top:14px;">
                <h3>Gerelateerde artikelen</h3>
                <?php if (empty($relatedPosts)) : ?>
                    <p class="muted">Geen gerelateerde artikelen gevonden.</p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($relatedPosts as $rel) : ?>
                            <li><a href="<?php echo htmlspecialchars($rel['permalink']); ?>"><?php echo htmlspecialchars($rel['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </article>

        <aside class="aside-sticky">
            <?php if (!empty($toc)) : ?>
                <section class="card toc">
                    <h3>Inhoudsopgave</h3>
                    <?php foreach ($toc as $item) : ?>
                        <a class="<?php echo $item['level']; ?>" href="#<?php echo htmlspecialchars($item['anchor']); ?>"><?php echo htmlspecialchars($item['text']); ?></a>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="card share">
                <h3>Deel dit artikel</h3>
                <a target="_blank" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($canonical); ?>">LinkedIn</a>
                <a target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonical); ?>">Facebook</a>
                <a target="_blank" href="https://x.com/intent/post?url=<?php echo urlencode($canonical); ?>&text=<?php echo urlencode($post['title'] ?? ''); ?>">X</a>
            </section>

            <section class="card">
                <h3>Categorieën</h3>
                <?php if (empty($categories)) : ?>
                    <p class="muted">Nog geen categorieën.</p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($categories as $cat) : ?>
                            <li><?php echo htmlspecialchars($cat['category']); ?> (<?php echo (int)$cat['total']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="card">
                <h3>Nieuwsbrief</h3>
                <p class="muted">Blijf op de hoogte van nieuwe artikelen.</p>
                <form>
                    <input type="email" placeholder="Email" style="width:100%; margin-bottom:8px;">
                    <button type="button">Inschrijven</button>
                </form>
            </section>

            <section class="card">
                <h3>Auteur</h3>
                <p><strong><?php echo htmlspecialchars($authorLabel); ?></strong></p>
                <p class="muted">Volg voor meer updates en artikelen.</p>
                <div class="row">
                    <a href="/contact">Contact</a>
                    <a href="/blog">Meer posts</a>
                </div>
            </section>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script>
    document.addEventListener('scroll', function () {
        var el = document.getElementById('article-content');
        var bar = document.getElementById('read-progress');
        if (!el || !bar) return;
        var rect = el.getBoundingClientRect();
        var total = el.offsetHeight - window.innerHeight;
        if (total <= 0) {
            bar.style.width = '100%';
            return;
        }
        var consumed = Math.min(Math.max(-rect.top, 0), total);
        bar.style.width = ((consumed / total) * 100) + '%';
    });
</script>
</body>
</html>
