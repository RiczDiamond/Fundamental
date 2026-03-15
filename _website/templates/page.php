<?php
    $post = $post ?? null;
    if (!$post) {
        echo '<p>Pagina niet gevonden.</p>';
        return;
    }
?>

<main class="content">
    <article class="page">
        <header>
            <h1><?php echo esc_html($post['post_title'] ?? ''); ?></h1>
        </header>

        <div class="page-content">
            <?php mol_render_post_content($post['post_content'] ?? ''); ?>
        </div>
    </article>
</main>
