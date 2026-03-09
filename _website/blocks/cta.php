<?php

$title = (string) ($section['fields']['title'] ?? 'Call to action');
$buttonLabel = (string) ($section['fields']['button_label'] ?? 'Lees meer');
$buttonUrl = (string) ($section['fields']['button_url'] ?? '/');

component_section_open('section-cta');
?>

<div class="container">

<?php
component_heading($title, 'h3');
component_link($buttonUrl, $buttonLabel);
?>


</div>

<?php
component_section_close();
