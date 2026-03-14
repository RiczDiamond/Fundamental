<?php

/**
 * Hero / intro section.
 *
 * Self‑describing schema for editor/dashboard.  When `_BLOCK_SCHEMA_ONLY`
 * is true the array is returned instead of rendering.
 *
 * Fields:
 *   - headline
 *   - subline (HTML allowed)
 */

$block_schema = [
    'label' => 'Hero',
    'hint' => 'Gebruikt: headline, subline',
    'fields' => [
        'headline' => [
            'type' => 'string',
            'label' => 'Headline',
            'default' => '',
            'placeholder' => 'Bijv. Welkom bij…',
        ],
        'subline'  => [
            'type' => 'html',
            'label' => 'Subtekst',
            'default' => '',
            'placeholder' => 'Korte introductie of missie',
        ],
    ],
];

if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('hero'),
    (array) ($section['fields'] ?? [])
);

$headline = (string) $fields['headline'];
$subline  = (string) $fields['subline'];

component_section_open('section-intro', $section['attrs'] ?? []);
?>

<div class="container intro">
    <?php if ($headline !== '') : ?>
        <h1><?php echo component_escape_html($headline); ?></h1>
    <?php endif; ?>

    <?php if ($subline !== '') : ?>
        <?php component_rich_text($subline, 'p'); ?>
    <?php endif; ?>
</div>

<?php

component_section_close();