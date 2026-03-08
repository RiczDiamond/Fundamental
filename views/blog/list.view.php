<?php
?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Fundamental CMS</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; color:#1f2937; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .layout { display:grid; grid-template-columns:1fr 280px; gap:14px; }
        .card { background:#fff; border-radius:10px; padding:16px; box-shadow: 0 2px 8px rgba(0,0,0,.05); margin-bottom: 12px; }
        a { color:#2563eb; text-decoration:none; }
        .muted { color:#64748b; font-size:13px; }
        h1, h2 { margin-top:0; }
        .tags { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
        .tag { background:#e2e8f0; color:#334155; border-radius:999px; padding:3px 8px; font-size:12px; }
        @media (max-width: 960px) { .layout { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<div class="container">
    <h1>Blog</h1>
    <?php if (!empty($_GET['category'])) : ?>
        <p class="muted">Filter: categorie <strong><?php echo htmlspecialchars($_GET['category']); ?></strong> · <a href="/blog">toon alles</a></p>
    <?php endif; ?>
    <div class="layout">
        <main>
            <?php if (empty($posts)) : ?>
                <div class="card">Er zijn nog geen gepubliceerde posts.</div>
            <?php else : ?>
                <?php foreach ($posts as $post) : ?>
                    <?php $tags = array_values(array_filter(array_map('trim', explode(',', (string)($post['tags'] ?? ''))))); ?>
                    <?php $readMinutes = max(1, (int)ceil(str_word_count(trim(strip_tags((string)($post['content'] ?? '')))) / 220)); ?>
                    <article class="card">
                        <h2>
                            <a href="<?php echo htmlspecialchars($post['permalink'] ?? ('/blog/' . ($post['slug'] ?? ''))); ?>">
                                <?php echo htmlspecialchars($post['title'] ?? ''); ?>
                            </a>
                        </h2>
                        <div class="muted">
                            <?php echo htmlspecialchars($post['published_at'] ?? $post['created_at'] ?? ''); ?>
                            · <?php echo $readMinutes; ?> min lezen
                            <?php if (!empty($post['category'])) : ?> · Categorie: <?php echo htmlspecialchars($post['category']); ?><?php endif; ?>
                        </div>
                        <?php if (!empty($post['featured_image'])) : ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" loading="lazy" style="width:100%; border-radius:8px; margin:10px 0;">
                        <?php endif; ?>
                        <?php if (!empty($post['intro'])) : ?>
                            <p><strong><?php echo nl2br(htmlspecialchars($post['intro'])); ?></strong></p>
                        <?php elseif (!empty($post['excerpt'])) : ?>
                            <p><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($tags)) : ?>
                            <div class="tags">
                                <?php foreach ($tags as $tag) : ?>
                                    <span class="tag">#<?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
        <aside>
            <section class="card">
                <h3>Categorieën</h3>
                <?php if (empty($categories)) : ?>
                    <p class="muted">Geen categorieën.</p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($categories as $category) : ?>
                            <li><?php echo htmlspecialchars($category['category']); ?> (<?php echo (int)$category['total']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
