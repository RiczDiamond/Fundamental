<?php
    $term = $term ?? null;
    $posts = $posts ?? [];

    if (!$term) {
        echo '<p>Term niet gevonden.</p>';
        return;
    }
?>

<main class="content">
    <h1>Categorie: <?php echo esc_html($term['name'] ?? ''); ?></h1>

    <?php if (empty($posts)): ?>
        <p>Geen berichten gevonden in deze categorie.</p>
    <?php else: ?>
        <ul class="post-list">
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="/<?php echo esc_attr($term['taxonomy']); ?>/<?php echo esc_attr($term['slug']); ?>/<?php echo esc_attr($post['post_name'] ?? $post['ID']); ?>">
                        <strong><?php echo esc_html($post['post_title'] ?? ''); ?></strong>
                    </a>
                    <span class="meta"><?php echo esc_html($post['post_date'] ?? ''); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
