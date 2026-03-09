<?php

if (!function_exists('component_escape_html')) {
    function component_escape_html(mixed $value): string {
        return esc_html($value);
    }
}

if (!function_exists('component_escape_attr')) {
    function component_escape_attr(mixed $value): string {
        return esc_attr($value);
    }
}

if (!function_exists('component_section_open')) {
    function component_section_open(string $className): void {
        $className = trim($className);
        echo '<section class="' . component_escape_attr($className) . '">';
    }
}

if (!function_exists('component_section_close')) {
    function component_section_close(): void {
        echo '</section>';
    }
}

if (!function_exists('component_heading')) {
    function component_heading(string $text, string $tag = 'h3', string $default = '', string $className = ''): void {
        $value = trim($text);

        if ($value === '') {
            $value = trim($default);
        }

        if ($value === '') {
            return;
        }

        $safeTag = strtolower(trim($tag));
        $allowed = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

        if (!in_array($safeTag, $allowed, true)) {
            $safeTag = 'h3';
        }

        $className = trim($className);
        $classAttr = $className !== '' ? ' class="' . component_escape_attr($className) . '"' : '';

        echo '<' . $safeTag . $classAttr . '>' . component_escape_html($value) . '</' . $safeTag . '>';
    }
}

if (!function_exists('component_paragraph')) {
    function component_paragraph(string $text, string $className = ''): void {
        $value = trim($text);

        if ($value === '') {
            return;
        }

        $className = trim($className);
        $classAttr = $className !== '' ? ' class="' . component_escape_attr($className) . '"' : '';

        echo '<p' . $classAttr . '>' . component_escape_html($value) . '</p>';
    }
}

if (!function_exists('component_rich_text')) {
    function component_rich_text(string $html, string $tag = 'div', string $className = ''): void {
        $value = trim($html);

        if ($value === '') {
            return;
        }

        $safeTag = strtolower(trim($tag));
        $allowed = ['div', 'section', 'article'];

        if (!in_array($safeTag, $allowed, true)) {
            $safeTag = 'div';
        }

        $className = trim($className);
        $classAttr = $className !== '' ? ' class="' . component_escape_attr($className) . '"' : '';

        echo '<' . $safeTag . $classAttr . '>' . $value . '</' . $safeTag . '>';
    }
}

if (!function_exists('component_link')) {
    function component_link(string $url, string $label, string $className = ''): void {
        $url = trim($url);
        $label = trim($label);

        if ($url === '' || $label === '') {
            return;
        }

        $className = trim($className);
        $classAttr = $className !== '' ? ' class="' . component_escape_attr($className) . '"' : '';

        echo '<a href="' . component_escape_attr($url) . '"' . $classAttr . '>' . component_escape_html($label) . '</a>';
    }
}
