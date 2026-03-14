<?php

/**
 * Mixed media + text block; optional image on left and optional link.
 */
$block_schema = [
    'hint'=>'Gebruikt: title, content, image, button_label, button_url',
    'fields'=>[
        'title'=>['type'=>'string','default'=>''],
        'content'=>['type'=>'html','default'=>''],
        'image'=>['type'=>'string','default'=>''],
        'button_label'=>['type'=>'string','default'=>''],
        'button_url'=>['type'=>'url','default'=>''],
    ],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('media-text'),
    (array) ($section['fields'] ?? [])
);

$title       = (string) $fields['title'];
$content     = (string) $fields['content'];
$image       = (string) $fields['image'];
$buttonLabel = (string) $fields['button_label'];
$buttonUrl   = (string) $fields['button_url'];

component_section_open('section-media-text', $section['attrs'] ?? []);
?>

<div class="container content columns">
    <?php component_heading($title, 'h3'); ?>

    <?php if ($image !== '') : ?>
        <div class="column">
            <p class="media"><img src="<?php echo component_escape_attr($image); ?>" alt="<?php echo component_escape_attr($title); ?>"></p>
        </div>
    <?php endif; ?>

    <div class="column">
        <?php component_rich_text($content, 'div'); ?>

        <?php if ($buttonLabel !== '' && $buttonUrl !== '') : ?>
            <p class="media-actions">
                <?php component_link($buttonUrl, $buttonLabel); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php component_section_close();
