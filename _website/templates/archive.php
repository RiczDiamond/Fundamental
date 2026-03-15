<?php
    // Archive template (shows a list of posts)
    $postType = $postType ?? 'post';
    $title = ucfirst($postType);
    $postsList = $posts ?? [];
?>

<main class="content">
    <h1><?php echo esc_html($title); ?> archief</h1>

    <?php if (empty($postsList)): ?>
        <p>Geen berichten gevonden.</p>
    <?php else: ?>
        <ul class="post-list">
            <?php foreach ($postsList as $post): ?>
                <li>
                    <a href="/<?php echo esc_attr($postType); ?>/<?php echo esc_attr($post['post_name'] ?? $post['ID']); ?>">
                        <strong><?php echo esc_html($post['post_title'] ?? ''); ?></strong>
                    </a>
                    <?php if (!empty($post['post_date'])): ?>
                        <span class="meta"><?php echo esc_html($post['post_date']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
