<?php

	if (!empty($not_found)) {
		render_404_section();
		return;
	}

	$posts = $posts ?? [];

	// Use WP-style loop helpers for consistent template API
	if (!empty($posts)) {
		setup_loop($posts);
		echo '<section class="content">';
		echo '<div class="head"><h1>Laatste berichten</h1></div>';
		echo '<div class="content"><div class="block">';
		echo '<ul class="posts">';
		while (have_posts()) {
			the_post();
			echo '<li class="post-item">';
			echo '<a class="post-title" href="' . esc(get_permalink()) . '">';
			the_title();
			echo '</a>';
			echo '<div class="excerpt">' . esc(get_the_excerpt()) . '</div>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div></div></section>';
		wp_reset_postdata();
	} else {
		render_posts_section('Laatste berichten', $posts, 'posts');
	}

?>