<?php

/**
 * Features section: titre plus puntjes.
 *
 * When the special flag is set this file returns its schema instead of
 * rendering, so the dashboard knows which fields to present.
 */

$block_schema = [
    'hint'=>'Gebruikt: title, content, items',
    'fields'=>[
        'title'=>['type'=>'string','default'=>''],
        'content'=>['type'=>'html','default'=>''],
        'items'=>['type'=>'list','default'=>[]],
    ],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('features'),
    (array) ($section['fields'] ?? [])
);

$title = (string) $fields['title'];
$intro = (string) $fields['content'];
$items = is_array($fields['items']) ? $fields['items'] : [];

component_section_open('section-features', $section['attrs'] ?? []);
echo '<div class="container services">';
echo '<div class="block">';
component_heading($title, 'h3');
component_rich_text($intro, 'div');

    if ($items !== []) {
        echo '<ul class="services-list">';
        foreach ($items as $item) {
            $label = trim((string) $item);
            if ($label === '') {
                continue;
            }
            echo '<li>';
            component_rich_text($label, 'span');
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '</div>'; // .block
    echo '</div>'; // .container
component_section_close();
