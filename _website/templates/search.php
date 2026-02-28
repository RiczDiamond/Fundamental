<section>
    <?php

        $query = $query ?? '';
        $posts = $posts ?? [];

        echo '<section class="content">';
        echo '<div class="head"><h1>Zoekresultaten voor: ' . esc($query) . '</h1></div>';
        echo '<div class="content"><div class="block">';

        if (empty($posts)) {
            echo '<p>Geen resultaten gevonden.</p>';
            echo '</div></div></section>';
            return;
        }

        echo '<ul class="search-results">';
        foreach ($posts as $p) {
            $t = esc($p['title'] ?? 'Untitled');
            $slug = esc($p['slug'] ?? '');
            echo '<li><a href="' . BASE_URL . '/' . $slug . '">' . $t . '</a></li>';
        }
        echo '</ul>';

        echo '</div></div></section>';

    ?>
</section>