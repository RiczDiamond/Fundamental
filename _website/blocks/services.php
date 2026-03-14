<?php

/**
 * Services listing.  Each item may include an optional bullet list.
 *
 * Exports schema for dashboard when `_BLOCK_SCHEMA_ONLY` is true.
 */

$block_schema = [
    'hint'=>'Gebruikt: title, intro, items',
    'fields'=>[
        'title'=>['type'=>'string','default'=>'Services'],
        'intro'=>['type'=>'html','default'=>''],
        'items'=>['type'=>'list','default'=>[]],
    ],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('services'),
    (array) ($section['fields'] ?? [])
);

$title = (string) $fields['title'];
$intro = (string) $fields['intro'];
$items = is_array($fields['items']) ? $fields['items'] : [];

component_section_open('section-services', $section['attrs'] ?? []);

echo '<div class="container services">';
    echo '<div class="block">';
    component_heading($title, 'h2');
    component_rich_text($intro, 'h4');

    foreach ($items as $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $stitle = (string) ($svc['title'] ?? '');
        $stext  = (string) ($svc['text'] ?? '');
        $slist  = is_array($svc['list']) ? $svc['list'] : [];

        echo '<div class="service">';
            component_heading($stitle, 'h3');
            component_rich_text($stext, 'p');
            component_render_list($slist);
        echo '</div>';
    }

    echo '</div>'; // .block
 echo '</div>'; // .container
component_section_close();

?>
