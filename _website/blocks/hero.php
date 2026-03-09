<?php

$headline = (string) ($section['fields']['headline'] ?? 'Hero titel');
$subline = (string) ($section['fields']['subline'] ?? '');

component_section_open('section-hero');
component_heading($headline, 'h2');

component_paragraph($subline);

component_section_close();
