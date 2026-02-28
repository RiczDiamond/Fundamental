<section>
	<?php

		$posts = $posts ?? [];
		$title = $title ?? 'Archief';

		if (empty($posts)) {
			echo '<section class="content"><div class="head"><h1>' . esc($title) . '</h1></div><div class="content"><div class="block"><p>Geen items in dit archief.</p></div></div></section>';
			return;
		}

		echo '<section class="content">';
		echo '<div class="head"><h1>' . esc($title) . '</h1></div>';
		echo '<div class="content"><div class="block">';

		echo '<ul class="archive-list">';
		foreach ($posts as $p) {
			$t = esc($p['title'] ?? 'Untitled');
			$slug = esc($p['slug'] ?? '');
			echo '<li><a href="' . BASE_URL . '/' . $slug . '">' . $t . '</a> ';
			if (!empty($p['created_at'])) {
				echo '<small>(' . esc($p['created_at']) . ')</small>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo '</div></div></section>';

	?>

</section>