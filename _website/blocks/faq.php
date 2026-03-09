<?php

$title = (string) ($section['fields']['title'] ?? 'Veelgestelde vragen');
$items = $section['fields']['items'] ?? [];

    if (!is_array($items) || $items === []) {
        $items = [
            ['question' => 'Hoe snel kunnen we live?', 'answer' => 'Meestal binnen enkele dagen met een basisopzet.'],
            ['question' => 'Kunnen we sections later uitbreiden?', 'answer' => 'Ja, je kunt altijd nieuwe section types toevoegen.'],
        ];
    }

component_section_open('section-faq');
component_heading($title, 'h3');

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $question = trim((string) ($item['question'] ?? ''));
        $answer = trim((string) ($item['answer'] ?? ''));

        if ($question === '' && $answer === '') {
            continue;
        }

        echo '<details>';

        if ($question !== '') {
            echo '<summary>' . component_escape_html($question) . '</summary>';
        }

        if ($answer !== '') {
            echo '<p>' . component_escape_html($answer) . '</p>';
        }

        echo '</details>';
    }

component_section_close();
