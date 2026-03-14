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
    /**
     * Open a section tag with a base classname and optional attributes.
     *
     * The second argument accepts an associative array of HTML attributes;
     * the `class` key will be merged with the base classname. This makes it
     * easy for a renderer to pass extra modifiers, IDs or data-attributes.
     */
    function component_section_open(string $className, array $attributes = []): void {
        $className = trim($className);
        $classes = [$className];

        if (isset($attributes['class'])) {
            $classes[] = $attributes['class'];
            unset($attributes['class']);
        }

        $attrPairs = [];
        $attrPairs[] = 'class="' . component_escape_attr(trim(implode(' ', $classes))) . '"';

        foreach ($attributes as $attr => $val) {
            $attr = strtolower(trim($attr));
            if ($attr === '') {
                continue;
            }
            $attrPairs[] = $attr . '="' . component_escape_attr($val) . '"';
        }

        echo '<section ' . implode(' ', $attrPairs) . '>';
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

        // make sure the HTML is safe before outputting
        if (function_exists('sanitize_section_html')) {
            $value = sanitize_section_html($value);
        } else {
            // fallback to a minimal whitelist
            $value = strip_tags($value,
                '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><blockquote><img>');
        }

        $safeTag = strtolower(trim($tag));
        $allowed = ['div', 'section', 'article', 'p', 'span', 'blockquote', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

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

// -----------------------------------------------------------------------------
// generic helpers for block templates
// -----------------------------------------------------------------------------

if (!function_exists('get_block_defaults')) {
    /**
     * Return a set of default field values for a given block type.
     *
     * This allows individual block templates to remain light-weight and keeps
     * the placeholder/sample content in a single location.  The values are
     * merged with any user-supplied fields before rendering.
     *
     * @param string $type block identifier (slug without extension)
     * @return array associative array of default fields
     */
    function get_block_defaults(string $type): array {
        // prefer defaults defined in the section schema; this allows the
        // information to live alongside the block template itself. the
        // loader (get_section_schemas) will merge in any `defaults` keys that
        // were provided by the file.
        $type = strtolower(trim($type));
        $schemas = function_exists('get_section_schemas') ? get_section_schemas() : [];
        if (isset($schemas[$type]['defaults']) && is_array($schemas[$type]['defaults'])) {
            return $schemas[$type]['defaults'];
        }

        // legacy fallback – kept for backwards compatibility until all
        // blocks are converted to self‑describing form.
        static $defaults = [
            'hero' => [
                'headline' => '',
                'subline'  => '',
            ],
            'content' => [
                'left' => [],
                'right' => [],
            ],
            'services' => [
                'title' => 'Services',
                'intro' => 'Complete webdevelopment services voor bedrijven die betere prestaties, design en online groei willen.',
                'items' => [],
            ],
            'faq' => [
                'title' => '',
                'items' => [],
            ],
            'features' => [
                'title' => '',
                'content' => '',
                'items' => [],
            ],
            'stats' => [
                'title' => '',
                'items' => [],
            ],
            'testimonial' => [
                'quote'  => '',
                'author' => '',
                'role'   => '',
            ],
            'portfolio' => [
                'items' => [],
            ],
            'contact' => [
                'options' => [],
                'text'    => '',
            ],
            'case-study' => [
                'title'    => 'Case study',
                'subtitle' => '',
                'text'     => '',
                'link'     => '',
            ],
            'media-text' => [
                'title'        => '',
                'content'      => '',
                'image'        => '',
                'button_label' => '',
                'button_url'   => '',
            ],
            'text' => [
                'title'   => '',
                'content' => '',
            ],
            'cta' => [
                'title'        => '',
                'button_label' => '',
                'button_url'   => '',
            ],
            // other block types may be added here as needed
        ];

        $type = strtolower(trim($type));
        return $defaults[$type] ?? [];
    }
}

if (!function_exists('component_render_list')) {
    /**
     * Output an unordered list from an array of strings with proper escaping.
     *
     * @param array $items
     * @param string $className optional class for the <ul>
     */
    function component_render_list(array $items, string $className = ''): void {
        if ($items === []) {
            return;
        }

        $className = trim($className);
        $classAttr = $className !== '' ? ' class="' . component_escape_attr($className) . '"' : '';

        echo '<ul' . $classAttr . '>';
        foreach ($items as $li) {
            echo '<li>' . component_escape_html((string) $li) . '</li>';
        }
        echo '</ul>';
    }
}

