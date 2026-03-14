<?php

/**
 * Simple portfolio grid split across two columns.
 *
 * The schema below is used by the editor/dashboard; during rendering it is
 * ignored.
 */

$block_schema = [
    'hint'=>'Gebruikt: items (image|title|subject|tags)',
    'fields'=>[
        'items'=>[
            'type'=>'list',
            'default'=>[],
            // optional sub‑schema describing each item object
            'item' => [
                'image'   => ['type'=>'string','default'=>''],
                'title'   => ['type'=>'string','default'=>''],
                'subject' => ['type'=>'string','default'=>''],
                'tags'    => ['type'=>'list','default'=>[]],
            ],
        ],
    ],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('portfolio'),
    (array) ($section['fields'] ?? [])
);

$items = is_array($fields['items']) ? $fields['items'] : [];

// legacy example items if nothing provided
if ($items === []) {
    $items = [
        ['image' => 'img/project1.jpg','title' => 'Web Development','subject' => 'E-commerce platform','tags' => ['PHP','API','SEO']],
        ['image' => 'img/project2.jpg','title' => 'Website','subject' => 'Startup landing page','tags' => ['HTML','CSS','JS']],
        ['image' => 'img/project3.jpg','title' => 'Web App','subject' => 'Analytics dashboard','tags' => ['Laravel','API','Vue']],
        ['image' => 'img/project4.jpg','title' => 'SEO Project','subject' => 'Performance optimalisatie','tags' => ['SEO','Speed']],
    ];
}

component_section_open('section-portfolio', $section['attrs'] ?? []);
echo '<div class="container portfolio">';
    echo '<div class="block">';
    echo '<div class="columns">';

    // split into two columns for simple layout
    $left = array_values(array_slice($items, 0, ceil(count($items)/2)));
    $right = array_values(array_slice($items, ceil(count($items)/2)));

    foreach (['left' => $left, 'right' => $right] as $col => $list) {
        echo '<div class="column">';
        foreach ($list as $it) {
            if (!is_array($it)) {
                continue;
            }
            $img    = (string) ($it['image'] ?? '');
            $title  = (string) ($it['title'] ?? '');
            $subject= (string) ($it['subject'] ?? '');
            $tags   = is_array($it['tags'] ?? []) ? $it['tags'] : [];

            echo '<a href="#">';
            echo '<span class="image"><img src="' . esc_attr($img) . '" alt="' . esc_attr($title) . '"></span>';
            echo '<span class="meta"><span class="title">' . component_escape_html($title) . '</span><span class="subject">' . component_escape_html($subject) . '</span></span>';
            if ($tags !== []) {
                echo '<span class="tags">';
                foreach ($tags as $tag) {
                    echo '<u>' . component_escape_html((string) $tag) . '</u>';
                }
                echo '</span>';
            }
            echo '</a>';
        }
        echo '</div>'; // .column
    }

    echo '</div>'; // .columns
    echo '</div>'; // .block
echo '</div>'; // .container

component_section_close();

?>
