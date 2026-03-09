<?php

$title = (string) ($section['fields']['title'] ?? '');
$content = (string) ($section['fields']['content'] ?? '');

component_section_open('section-text');

?>



<div class="container">

<?php


component_heading($title, 'h3');
component_rich_text($content, 'div');

?>
</div>





<?php

component_section_close();
