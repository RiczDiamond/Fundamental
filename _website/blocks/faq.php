<?php

/**
 * FAQ section with question/answer pairs.
 *
 * Schema is exported when `_BLOCK_SCHEMA_ONLY` is true for dashboard
 * discovery.
 *
 * Fields:
 *   - title
 *   - items: [{question,answer},...]
 */

$block_schema = [
    'label' => 'FAQ',
    'hint' => 'Gebruikt: title, items (vraag/antwoord)',
    'fields' => [
        'title' => [
            'type' => 'string',
            'label' => 'Titel',
            'default' => '',
            'placeholder' => 'Bijv. Veelgestelde vragen',
        ],
        'items' => [
            'type' => 'list',
            'label' => 'Vraag & antwoord',
            'default' => [],
        ],
        'items_lines' => [
            'type' => 'textarea',
            'label' => 'Items (regel per vraag/antwoord)',
            'default' => '',
            'placeholder' => 'vraag|antwoord per regel',
        ],
    ],
    'editor_fields' => ['title', 'items_lines'],
    'data_fields' => ['title', 'items'],
];
if (!empty($GLOBALS['_BLOCK_SCHEMA_ONLY'])) {
    return $block_schema;
}

$fields = array_merge(
    get_block_defaults('faq'),
    (array) ($section['fields'] ?? [])
);

$title = (string) $fields['title'];
$items = is_array($fields['items']) ? $fields['items'] : [];

if ($items === []) {
    // keep the old sample questions for backwards compatibility
    $items = [
        ['question' => 'Hoe snel kunnen we live?', 'answer' => 'Meestal binnen enkele dagen met een basisopzet.'],
        ['question' => 'Kunnen we sections later uitbreiden?', 'answer' => 'Ja, je kunt altijd nieuwe section types toevoegen.'],
    ];
}

component_section_open('section-faq', $section['attrs'] ?? []);
echo '<div class="container content">';
echo '<div class="block">';
component_heading($title, 'h3');

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $question = trim((string) ($item['question'] ?? ''));
    $answer   = trim((string) ($item['answer'] ?? ''));

    if ($question === '' && $answer === '') {
        continue;
    }

    echo '<details class="faq-item">';
    if ($question !== '') {
        echo '<summary class="faq-question">' . component_escape_html($question) . '</summary>';
    }
    if ($answer !== '') {
        echo '<p class="faq-answer">' . component_escape_html($answer) . '</p>';
    }
    echo '</details>';
}

echo '</div>'; // .block
echo '</div>'; // .container
component_section_close();
