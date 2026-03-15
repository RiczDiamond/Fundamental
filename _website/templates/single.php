<?php
    $post = $post ?? null;
    if (!$post) {
        echo '<p>Bericht niet gevonden.</p>';
        return;
    }
?>

<main class="content">
    <article class="post">
        <header>
            <h1><?php echo esc_html($post['post_title'] ?? ''); ?></h1>
            <div class="post-meta">
                <?php if (!empty($post['post_date'])): ?>
                    <time datetime="<?php echo esc_attr($post['post_date']); ?>"><?php echo esc_html($post['post_date']); ?></time>
                <?php endif; ?>
                <?php if (!empty($post['post_author'])): ?>
                    <span class="post-author">Auteur: <?php echo esc_html((string) $post['post_author']); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <div class="post-content">
            <?php mol_render_post_content($post['post_content'] ?? ''); ?>
        </div>

        <?php if (!empty($post['meta']) && is_array($post['meta'])): ?>
            <section class="post-meta-fields">
                <h2>Extra velden</h2>
                <ul>
                    <?php foreach ($post['meta'] as $key => $value): ?>
                        <li><strong><?php echo esc_html($key); ?>:</strong> <?php echo esc_html((string) $value); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (!empty($post['terms']) && is_array($post['terms'])): ?>
            <section class="post-terms">
                <h2>Categorieën / tags</h2>
                <ul>
                    <?php foreach ($post['terms'] as $term): ?>
                        <li><?php echo esc_html($term['name']); ?> (<?php echo esc_html($term['taxonomy']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </article>
</main>
