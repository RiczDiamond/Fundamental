<?php

    $title = (string) ($section['fields']['title'] ?? 'Veelgestelde vragen');
    $items = $section['fields']['items'] ?? [];

    if (!is_array($items) || $items === []) {
        $items = [
            ['question' => 'Hoe snel kunnen we live?', 'answer' => 'Meestal binnen enkele dagen met een basisopzet.'],
            ['question' => 'Kunnen we sections later uitbreiden?', 'answer' => 'Ja, je kunt altijd nieuwe section types toevoegen.'],
        ];
    }

    echo '<section class="section-faq">';

    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }

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
            echo '<summary>' . htmlspecialchars($question, ENT_QUOTES, 'UTF-8') . '</summary>';
        }

        if ($answer !== '') {
            echo '<p>' . htmlspecialchars($answer, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        echo '</details>';
    }

    echo '</section>';
