<?php
?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Fundamental CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="site-shell">
<?php $siteHeaderTitle = 'Fundamental CMS'; require __DIR__ . '/../partials/header.php'; ?>
<main class="container py-4 site-main">
    <section class="site-intro mb-4">
        <p class="site-kicker">Stories & updates</p>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <h1 class="site-title h2 mb-2">Blog</h1>
                <p class="site-lead mb-0">Artikelen, inzichten en updates in een rustiger editorial jasje in plaats van standaard dashboard-kaarten.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="/">Terug naar home</a>
        </div>
    </section>

    <?php if (!empty($_GET['category'])) : ?>
        <div class="alert alert-info py-2">
            Filter: categorie <strong><?php echo htmlspecialchars($_GET['category']); ?></strong> ·
            <a class="alert-link" href="/blog">toon alles</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <main class="col-12 col-lg-8">
            <?php if (empty($posts)) : ?>
                <div class="card border-0 shadow-sm rounded-4 site-panel">
                    <div class="card-body">Er zijn nog geen gepubliceerde posts.</div>
                </div>
            <?php else : ?>
                <?php foreach ($posts as $post) : ?>
                    <?php $tags = array_values(array_filter(array_map('trim', explode(',', (string)($post['tags'] ?? ''))))); ?>
                    <?php $readMinutes = max(1, (int)ceil(str_word_count(trim(strip_tags((string)($post['content'] ?? '')))) / 220)); ?>
                    <article class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden site-panel">
                        <?php if (!empty($post['featured_image'])) : ?>
                            <img class="img-fluid" src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="card-body p-4">
                            <h2 class="h4 mb-2">
                                <a class="text-decoration-none" href="<?php echo htmlspecialchars($post['permalink'] ?? ('/blog/' . ($post['slug'] ?? ''))); ?>">
                                <?php echo htmlspecialchars($post['title'] ?? ''); ?>
                                </a>
                            </h2>
                            <div class="small text-secondary mb-3">
                                <?php echo htmlspecialchars($post['published_at'] ?? $post['created_at'] ?? ''); ?>
                                · <?php echo $readMinutes; ?> min lezen
                                <?php if (!empty($post['category'])) : ?> · Categorie: <?php echo htmlspecialchars($post['category']); ?><?php endif; ?>
                            </div>

                            <?php if (!empty($post['intro'])) : ?>
                                <p class="mb-2"><strong><?php echo nl2br(htmlspecialchars($post['intro'])); ?></strong></p>
                            <?php elseif (!empty($post['excerpt'])) : ?>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($tags)) : ?>
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <?php foreach ($tags as $tag) : ?>
                                        <span class="badge text-bg-light border">#<?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <a class="btn btn-primary" href="<?php echo htmlspecialchars($post['permalink'] ?? ('/blog/' . ($post['slug'] ?? ''))); ?>">Lees artikel</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <aside class="col-12 col-lg-4">
            <section class="card border-0 shadow-sm rounded-4 site-panel">
                <div class="card-body">
                <h3 class="h5">Categorieën</h3>
                <?php if (empty($categories)) : ?>
                    <p class="text-secondary mb-0">Geen categorieën.</p>
                <?php else : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($categories as $category) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?php echo htmlspecialchars($category['category']); ?></span>
                                <span class="badge text-bg-secondary"><?php echo (int)$category['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
