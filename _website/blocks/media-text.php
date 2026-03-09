<?php

$title = (string) ($section['fields']['title'] ?? 'Media met tekst');
$content = (string) ($section['fields']['content'] ?? '');
$image = (string) ($section['fields']['image'] ?? '');
$buttonLabel = (string) ($section['fields']['button_label'] ?? '');
$buttonUrl = (string) ($section['fields']['button_url'] ?? '');

component_section_open('section-media-text');
?>

<div class="container">
    <?php
    component_heading($title, 'h3');

if ($image !== '') {
    echo '<p><img src="' . component_escape_attr($image) . '" alt="' . component_escape_attr($title) . '"></p>';
}

component_rich_text($content, 'div');

if ($buttonLabel !== '' && $buttonUrl !== '') {
    echo '<p>';
    component_link($buttonUrl, $buttonLabel);
    echo '</p>';
}
?>

</div>

<?php
component_section_close();
