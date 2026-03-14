<?php

/**
 * Highlight a single case study; title, subtitle, description and a link.
 *
 * This template exports a schema array when `$GLOBALS['_BLOCK_SCHEMA_ONLY']`
 * is true.  The dashboard and normalization logic consume that metadata to
 * build forms and populate defaults.
 *
 * Fields:
 *   - title
 *   - subtitle
 *   - text   (HTML allowed)
 *   - link   (URL)
 */

$block_schema = [
    'hint' => 'Gebruikt: title, subtitle, text, link',
    'fields' => [
        'title'    => ['type' => 'string', 'default' => 'Case study'],
        'subtitle' => ['type' => 'string', 'default' => ''],
        'text'     => ['type' => 'html',   'default' => ''],
        'link'     => ['type' => 'url',    'default' => ''],
    ],
];

if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('case-study'),
    (array) ($section['fields'] ?? [])
);

$title    = (string) $fields['title'];
$subtitle = (string) $fields['subtitle'];
$text     = (string) $fields['text'];
$link     = (string) $fields['link'];

component_section_open('section-case-study', $section['attrs'] ?? []);

echo '<div class="container head">';
echo '<div class="block">';

component_heading($title, 'h1');
component_heading($subtitle, 'h2');
component_rich_text($text, 'p');
if ($link !== '') {
    echo '<a class="dribbble" href="' . esc_attr($link) . '">Bekijk design op Dribbble</a>';
}

echo '</div>'; // .block
echo '</div>'; // .container

component_section_close();

?>
