<?php

$title = (string) ($section['fields']['title'] ?? '');
$content = (string) ($section['fields']['content'] ?? '');

component_section_open('section-text');
component_heading($title, 'h3');
component_rich_text($content, 'div');

component_section_close();
