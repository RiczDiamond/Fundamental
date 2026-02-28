<section>

<?php

	$category = $category ?? '';
	$posts = $posts ?? [];

	echo '<section class="content">';
	echo '<div class="head"><h1>Categorie: ' . esc($category) . '</h1></div>';
	echo '<div class="content"><div class="block">';

	if (empty($posts)) {
		echo '<p>Geen berichten voor deze categorie.</p>';
		echo '</div></div></section>';
		return;
	}

	echo '<ul class="category-list">';
	foreach ($posts as $p) {
		$t = esc($p['title'] ?? 'Untitled');
		$slug = esc($p['slug'] ?? '');
		echo '<li><a href="' . BASE_URL . '/' . $slug . '">' . $t . '</a></li>';
	}
	echo '</ul>';

	echo '</div></div></section>';

?>

</section>