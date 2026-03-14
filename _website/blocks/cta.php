<?php

/**
 * Call-to-action block: headline with an optional button.
 *
 * Iniţialisatieformulier‑schema live in dit bestand.
 */

$block_schema = [
    'hint' => 'Gebruikt: title, button_label, button_url',
    'fields' => [
        'title'        => ['type'=>'string','default'=>''],
        'button_label' => ['type'=>'string','default'=>''],
        'button_url'   => ['type'=>'url','default'=>''],
    ],
];

if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('cta'),
    (array) ($section['fields'] ?? [])
);

$title       = (string) $fields['title'];
$buttonLabel = (string) $fields['button_label'];
$buttonUrl   = (string) $fields['button_url'];

component_section_open('section-cta', $section['attrs'] ?? []);
?>

<div class="container block">
    <?php
    component_heading($title, 'h3');
    echo '<p class="cta-actions">';
    component_link($buttonUrl, $buttonLabel);
    echo '</p>';
    ?>
</div>

<?php
component_section_close();
