<?php

/**
 * Simple text block with optional heading and rich HTML paragraph(s).
 */
$block_schema = [
    'hint'=>'Gebruikt: title, content',
    'fields'=>[
        'title'=>['type'=>'string','default'=>''],
        'content'=>['type'=>'html','default'=>''],
    ],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('text'),
    (array) ($section['fields'] ?? [])
);
$title = (string) $fields['title'];
$content = (string) $fields['content'];

component_section_open('section-text', $section['attrs'] ?? []);
?>

<div class="container content block">
    <?php
    component_heading($title, 'h3');
    component_rich_text($content, 'div');
    ?>
</div>

<?php
component_section_close();
