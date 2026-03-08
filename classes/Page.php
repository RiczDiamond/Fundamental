<?php

class Page {

    private $link;
    private $table = 'pages';
    private $tableExistsCache = [];
    private $columnExistsCache = [];
    private $lastError = '';

    public function __construct($link)
    {
        $this->link = $link;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function clearLastError()
    {
        $this->lastError = '';
    }

    private function setLastError($message)
    {
        $this->lastError = trim((string)$message);
        return false;
    }

    private function tableExists($table)
    {
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $stmt = $this->link->prepare(
                'SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table]);
            $exists = (bool)$stmt->fetchColumn();
            $this->tableExistsCache[$table] = $exists;
            return $exists;
        } catch (Throwable $e) {
            $this->tableExistsCache[$table] = false;
            return false;
        }
    }

    private function slugify($value)
    {
        $value = trim((string)$value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'page';
    }

    private function uniqueSlug($baseSlug, $excludeId = null)
    {
        $baseSlug = $this->slugify($baseSlug);
        $slug = $baseSlug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM {$this->table} WHERE slug = ?";
            $params = [$slug];

            if ($excludeId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = (int)$excludeId;
            }

            $sql .= ' LIMIT 1';
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }

    private function columnExists($table, $column)
    {
        $table = trim((string)$table);
        $column = trim((string)$column);
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->link->prepare(
                'SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            $exists = (bool)$stmt->fetchColumn();
            $this->columnExistsCache[$cacheKey] = $exists;
            return $exists;
        } catch (Throwable $e) {
            $this->columnExistsCache[$cacheKey] = false;
            return false;
        }
    }

    private function normalizeStatus($status)
    {
        $status = trim((string)$status);
        $allowed = ['draft', 'published', 'archived'];
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function normalizeDateTime($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizePageType($value)
    {
        $value = strtolower(trim((string)$value));
        $allowed = ['basic_page', 'landing_page', 'contact_page'];
        return in_array($value, $allowed, true) ? $value : 'basic_page';
    }

    private function normalizeTemplate($value, $pageType)
    {
        $value = strtolower(trim((string)$value));
        $allowed = ['default', 'landing', 'contact'];
        if (!in_array($value, $allowed, true)) {
            if ($pageType === 'landing_page') {
                return 'landing';
            }
            if ($pageType === 'contact_page') {
                return 'contact';
            }
            return 'default';
        }
        return $value;
    }

    private function allowedBlockTypesByPageType($pageType)
    {
        if ($pageType === 'landing_page') {
            return ['hero', 'text', 'image', 'gallery', 'cta', 'quote', 'columns', 'spacer'];
        }

        if ($pageType === 'contact_page') {
            return ['hero', 'text', 'contact_form', 'map', 'cta', 'spacer'];
        }

        return ['text', 'image', 'quote', 'cta', 'spacer'];
    }

    public function getTemplatePresetConfigurations()
    {
        return [
            'home_standard' => [
                'label' => 'Home - standaard',
                'description' => 'Hero, drie highlights en een CTA.',
                'page_type' => 'landing_page',
                'template' => 'landing',
                'fields' => [
                    ['name' => 'hero_title', 'label' => 'Hero titel', 'type' => 'text', 'required' => true, 'default' => 'Welkom op onze website'],
                    ['name' => 'hero_subtitle', 'label' => 'Hero subtitel', 'type' => 'textarea', 'required' => false, 'default' => 'Wij helpen je snel en persoonlijk verder.'],
                    ['name' => 'cta_label', 'label' => 'Knop tekst', 'type' => 'text', 'required' => true, 'default' => 'Neem contact op'],
                    ['name' => 'cta_url', 'label' => 'Knop URL', 'type' => 'text', 'required' => true, 'default' => '/contact'],
                    ['name' => 'highlight_1_title', 'label' => 'Highlight 1 titel', 'type' => 'text', 'required' => true, 'default' => 'Snel live'],
                    ['name' => 'highlight_1_text', 'label' => 'Highlight 1 tekst', 'type' => 'textarea', 'required' => true, 'default' => 'Binnen korte tijd online met een duidelijke pagina.'],
                    ['name' => 'highlight_2_title', 'label' => 'Highlight 2 titel', 'type' => 'text', 'required' => true, 'default' => 'Professioneel'],
                    ['name' => 'highlight_2_text', 'label' => 'Highlight 2 tekst', 'type' => 'textarea', 'required' => true, 'default' => 'Een strakke uitstraling die vertrouwen geeft.'],
                    ['name' => 'highlight_3_title', 'label' => 'Highlight 3 titel', 'type' => 'text', 'required' => true, 'default' => 'Meetbaar resultaat'],
                    ['name' => 'highlight_3_text', 'label' => 'Highlight 3 tekst', 'type' => 'textarea', 'required' => true, 'default' => 'Focus op aanvragen, leads en duidelijke doelen.'],
                ],
            ],
            'about_company' => [
                'label' => 'Over ons',
                'description' => 'Intro met verhaal en kernwaarden.',
                'page_type' => 'basic_page',
                'template' => 'default',
                'fields' => [
                    ['name' => 'intro_title', 'label' => 'Intro titel', 'type' => 'text', 'required' => true, 'default' => 'Wie wij zijn'],
                    ['name' => 'intro_text', 'label' => 'Intro tekst', 'type' => 'textarea', 'required' => true, 'default' => 'Wij zijn een betrokken team met focus op kwaliteit.'],
                    ['name' => 'story_title', 'label' => 'Verhaal titel', 'type' => 'text', 'required' => true, 'default' => 'Ons verhaal'],
                    ['name' => 'story_text', 'label' => 'Verhaal tekst', 'type' => 'textarea', 'required' => true, 'default' => 'Van klein begin naar betrouwbare partner voor klanten.'],
                    ['name' => 'value_1', 'label' => 'Kernwaarde 1', 'type' => 'text', 'required' => true, 'default' => 'Transparant'],
                    ['name' => 'value_2', 'label' => 'Kernwaarde 2', 'type' => 'text', 'required' => true, 'default' => 'Persoonlijk'],
                    ['name' => 'value_3', 'label' => 'Kernwaarde 3', 'type' => 'text', 'required' => true, 'default' => 'Betrouwbaar'],
                ],
            ],
            'contact_simple' => [
                'label' => 'Contact',
                'description' => 'Korte intro, contactformulier en kaart.',
                'page_type' => 'contact_page',
                'template' => 'contact',
                'fields' => [
                    ['name' => 'hero_title', 'label' => 'Kop', 'type' => 'text', 'required' => true, 'default' => 'Neem contact op'],
                    ['name' => 'hero_subtitle', 'label' => 'Subkop', 'type' => 'textarea', 'required' => false, 'default' => 'We reageren meestal binnen 1 werkdag.'],
                    ['name' => 'form_title', 'label' => 'Formulier titel', 'type' => 'text', 'required' => true, 'default' => 'Stuur ons een bericht'],
                    ['name' => 'form_email_to', 'label' => 'Ontvangst e-mail', 'type' => 'text', 'required' => false, 'default' => ''],
                    ['name' => 'map_title', 'label' => 'Kaart titel', 'type' => 'text', 'required' => true, 'default' => 'Onze locatie'],
                    ['name' => 'map_embed_url', 'label' => 'Google Maps embed URL', 'type' => 'text', 'required' => false, 'default' => ''],
                ],
            ],
        ];
    }

    private function fillPresetDefaults($presetKey, array $payload)
    {
        $configs = $this->getTemplatePresetConfigurations();
        if (!isset($configs[$presetKey])) {
            return [];
        }

        $filled = [];
        $fields = isset($configs[$presetKey]['fields']) && is_array($configs[$presetKey]['fields'])
            ? $configs[$presetKey]['fields']
            : [];

        foreach ($fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $value = array_key_exists($name, $payload) ? (string)$payload[$name] : (string)($field['default'] ?? '');
            $filled[$name] = trim($value);
        }

        return $filled;
    }

    private function buildBuilderFromPreset($presetKey, array $payload, &$builderJson, &$error)
    {
        $builderJson = null;
        $error = '';

        $configs = $this->getTemplatePresetConfigurations();
        if (!isset($configs[$presetKey])) {
            $error = 'onbekende template preset';
            return false;
        }

        $data = $this->fillPresetDefaults($presetKey, $payload);
        $blocks = [];

        if ($presetKey === 'home_standard') {
            $blocks[] = [
                'type' => 'hero',
                'data' => [
                    'title' => $data['hero_title'] ?? 'Welkom op onze website',
                    'subtitle' => $data['hero_subtitle'] ?? '',
                    'cta_label' => $data['cta_label'] ?? 'Neem contact op',
                    'cta_url' => $data['cta_url'] ?? '/contact',
                ],
            ];
            $blocks[] = [
                'type' => 'columns',
                'data' => [
                    'columns' => [
                        ['heading' => $data['highlight_1_title'] ?? '', 'content' => $data['highlight_1_text'] ?? ''],
                        ['heading' => $data['highlight_2_title'] ?? '', 'content' => $data['highlight_2_text'] ?? ''],
                        ['heading' => $data['highlight_3_title'] ?? '', 'content' => $data['highlight_3_text'] ?? ''],
                    ],
                ],
            ];
            $blocks[] = [
                'type' => 'cta',
                'data' => [
                    'label' => $data['cta_label'] ?? 'Neem contact op',
                    'url' => $data['cta_url'] ?? '/contact',
                    'style' => 'primary',
                ],
            ];
        } elseif ($presetKey === 'about_company') {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'content' => ($data['intro_title'] ?? 'Wie wij zijn') . "\n\n" . ($data['intro_text'] ?? ''),
                ],
            ];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'content' => ($data['story_title'] ?? 'Ons verhaal') . "\n\n" . ($data['story_text'] ?? ''),
                ],
            ];
            $blocks[] = [
                'type' => 'quote',
                'data' => [
                    'quote' => implode(' - ', array_filter([
                        (string)($data['value_1'] ?? ''),
                        (string)($data['value_2'] ?? ''),
                        (string)($data['value_3'] ?? ''),
                    ])),
                    'author' => 'Onze kernwaarden',
                ],
            ];
        } elseif ($presetKey === 'contact_simple') {
            $blocks[] = [
                'type' => 'hero',
                'data' => [
                    'title' => $data['hero_title'] ?? 'Neem contact op',
                    'subtitle' => $data['hero_subtitle'] ?? '',
                ],
            ];
            $blocks[] = [
                'type' => 'contact_form',
                'data' => [
                    'title' => $data['form_title'] ?? 'Stuur ons een bericht',
                    'email_to' => $data['form_email_to'] ?? '',
                ],
            ];
            if (!empty($data['map_embed_url'])) {
                $blocks[] = [
                    'type' => 'map',
                    'data' => [
                        'title' => $data['map_title'] ?? 'Onze locatie',
                        'embed_url' => $data['map_embed_url'] ?? '',
                    ],
                ];
            }
        }

        $encoded = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $error = 'kon preset blocks niet serialiseren';
            return false;
        }

        $builderJson = $encoded;
        return true;
    }

    private function parseTemplatePayload($raw)
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeTemplatePreset($value)
    {
        $value = strtolower(trim((string)$value));
        if ($value === '') {
            return null;
        }

        $configs = $this->getTemplatePresetConfigurations();
        return array_key_exists($value, $configs) ? $value : null;
    }

    private function normalizeTemplatePayloadJson($raw)
    {
        $payload = [];

        if (is_array($raw)) {
            $payload = $raw;
        } elseif (is_string($raw)) {
            $payload = $this->parseTemplatePayload($raw);
        }

        if (empty($payload)) {
            return null;
        }

        $hero = isset($payload['hero']) && is_array($payload['hero']) ? $payload['hero'] : [];
        $normalizedHero = [];

        $heroTitle = substr(trim((string)($hero['title'] ?? '')), 0, 180);
        if ($heroTitle !== '') {
            $normalizedHero['title'] = $heroTitle;
        }

        $heroSubtitle = substr(trim((string)($hero['subtitle'] ?? '')), 0, 500);
        if ($heroSubtitle !== '') {
            $normalizedHero['subtitle'] = $heroSubtitle;
        }

        $ctaLabel = substr(trim((string)($hero['cta_label'] ?? '')), 0, 120);
        $ctaUrl = substr(trim((string)($hero['cta_url'] ?? '')), 0, 255);
        if ($ctaLabel !== '' && $ctaUrl !== '' && $this->isAllowedLink($ctaUrl)) {
            $normalizedHero['cta_label'] = $ctaLabel;
            $normalizedHero['cta_url'] = $ctaUrl;
        }

        if (empty($normalizedHero)) {
            return null;
        }

        $normalized = ['hero' => $normalizedHero];
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    private function isAllowedLink($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }
        return (bool)preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $value);
    }

    private function validateBuilderJson($rawBuilderJson, $pageType, &$normalizedJson, &$error)
    {
        $normalizedJson = null;
        $error = '';

        if ($rawBuilderJson === null || trim((string)$rawBuilderJson) === '') {
            return true;
        }

        if (!is_string($rawBuilderJson)) {
            $error = 'verwachte JSON-string';
            return false;
        }

        $decoded = json_decode($rawBuilderJson, true);
        if (!is_array($decoded)) {
            $error = 'JSON moet een array van blocks zijn';
            return false;
        }

        if (count($decoded) > 120) {
            $error = 'te veel blocks (max 120)';
            return false;
        }

        $allowedTypes = $this->allowedBlockTypesByPageType($pageType);
        $normalizedBlocks = [];

        foreach (array_values($decoded) as $index => $block) {
            if (!is_array($block)) {
                $error = 'block #' . ($index + 1) . ' is geen object';
                return false;
            }

            $type = strtolower(trim((string)($block['type'] ?? '')));
            if ($type === '') {
                $error = 'block #' . ($index + 1) . ' mist type';
                return false;
            }

            if (!in_array($type, $allowedTypes, true)) {
                $error = 'block type niet toegestaan voor dit page type: ' . $type;
                return false;
            }

            $data = (isset($block['data']) && is_array($block['data'])) ? $block['data'] : $block;
            unset($data['type'], $data['id']);

            $normalizedBlock = ['type' => $type, 'data' => []];

            if ($type === 'hero') {
                $title = trim((string)($data['title'] ?? ''));
                if ($title === '') {
                    $error = 'hero block #' . ($index + 1) . ' vereist title';
                    return false;
                }
                $normalizedBlock['data']['title'] = substr($title, 0, 180);
                $normalizedBlock['data']['subtitle'] = substr(trim((string)($data['subtitle'] ?? '')), 0, 400);
                $ctaLabel = trim((string)($data['cta_label'] ?? ''));
                $ctaUrl = trim((string)($data['cta_url'] ?? ''));
                if ($ctaLabel !== '' || $ctaUrl !== '') {
                    if ($ctaLabel === '' || !$this->isAllowedLink($ctaUrl)) {
                        $error = 'hero block #' . ($index + 1) . ' heeft ongeldige CTA';
                        return false;
                    }
                    $normalizedBlock['data']['cta_label'] = substr($ctaLabel, 0, 120);
                    $normalizedBlock['data']['cta_url'] = substr($ctaUrl, 0, 255);
                }
            } elseif ($type === 'text') {
                $content = trim((string)($data['content'] ?? ''));
                if ($content === '') {
                    $error = 'text block #' . ($index + 1) . ' vereist content';
                    return false;
                }
                $normalizedBlock['data']['content'] = $content;
            } elseif ($type === 'image') {
                $src = trim((string)($data['src'] ?? ''));
                if (!$this->isAllowedLink($src)) {
                    $error = 'image block #' . ($index + 1) . ' vereist geldige src';
                    return false;
                }
                $normalizedBlock['data']['src'] = substr($src, 0, 255);
                $normalizedBlock['data']['alt'] = substr(trim((string)($data['alt'] ?? '')), 0, 255);
                $normalizedBlock['data']['caption'] = substr(trim((string)($data['caption'] ?? '')), 0, 300);
            } elseif ($type === 'gallery') {
                $images = $data['images'] ?? null;
                if (!is_array($images) || empty($images)) {
                    $error = 'gallery block #' . ($index + 1) . ' vereist images[]';
                    return false;
                }
                if (count($images) > 24) {
                    $error = 'gallery block #' . ($index + 1) . ' max 24 images';
                    return false;
                }
                $normalizedImages = [];
                foreach (array_values($images) as $imgIndex => $img) {
                    if (!is_array($img)) {
                        $error = 'gallery block #' . ($index + 1) . ' image #' . ($imgIndex + 1) . ' is ongeldig';
                        return false;
                    }
                    $src = trim((string)($img['src'] ?? ''));
                    if (!$this->isAllowedLink($src)) {
                        $error = 'gallery block #' . ($index + 1) . ' image #' . ($imgIndex + 1) . ' vereist geldige src';
                        return false;
                    }
                    $normalizedImages[] = [
                        'src' => substr($src, 0, 255),
                        'alt' => substr(trim((string)($img['alt'] ?? '')), 0, 255),
                    ];
                }
                $normalizedBlock['data']['images'] = $normalizedImages;
            } elseif ($type === 'cta') {
                $label = trim((string)($data['label'] ?? ''));
                $url = trim((string)($data['url'] ?? ''));
                if ($label === '' || !$this->isAllowedLink($url)) {
                    $error = 'cta block #' . ($index + 1) . ' vereist label en geldige url';
                    return false;
                }
                $normalizedBlock['data']['label'] = substr($label, 0, 120);
                $normalizedBlock['data']['url'] = substr($url, 0, 255);
                $style = strtolower(trim((string)($data['style'] ?? 'primary')));
                if (!in_array($style, ['primary', 'secondary'], true)) {
                    $style = 'primary';
                }
                $normalizedBlock['data']['style'] = $style;
            } elseif ($type === 'quote') {
                $quote = trim((string)($data['quote'] ?? ''));
                if ($quote === '') {
                    $error = 'quote block #' . ($index + 1) . ' vereist quote';
                    return false;
                }
                $normalizedBlock['data']['quote'] = substr($quote, 0, 600);
                $normalizedBlock['data']['author'] = substr(trim((string)($data['author'] ?? '')), 0, 120);
            } elseif ($type === 'columns') {
                $columns = $data['columns'] ?? null;
                if (!is_array($columns) || count($columns) < 2 || count($columns) > 4) {
                    $error = 'columns block #' . ($index + 1) . ' vereist 2-4 kolommen';
                    return false;
                }
                $normalizedColumns = [];
                foreach (array_values($columns) as $colIndex => $column) {
                    if (!is_array($column)) {
                        $error = 'columns block #' . ($index + 1) . ' kolom #' . ($colIndex + 1) . ' is ongeldig';
                        return false;
                    }
                    $content = trim((string)($column['content'] ?? ''));
                    if ($content === '') {
                        $error = 'columns block #' . ($index + 1) . ' kolom #' . ($colIndex + 1) . ' vereist content';
                        return false;
                    }
                    $normalizedColumns[] = [
                        'heading' => substr(trim((string)($column['heading'] ?? '')), 0, 120),
                        'content' => $content,
                    ];
                }
                $normalizedBlock['data']['columns'] = $normalizedColumns;
            } elseif ($type === 'spacer') {
                $size = strtolower(trim((string)($data['size'] ?? 'md')));
                if (!in_array($size, ['sm', 'md', 'lg', 'xl'], true)) {
                    $size = 'md';
                }
                $normalizedBlock['data']['size'] = $size;
            } elseif ($type === 'contact_form') {
                $title = trim((string)($data['title'] ?? 'Contacteer ons'));
                $normalizedBlock['data']['title'] = substr($title, 0, 180);
                $emailTo = trim((string)($data['email_to'] ?? ''));
                if ($emailTo !== '' && !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
                    $error = 'contact_form block #' . ($index + 1) . ' bevat ongeldig email_to';
                    return false;
                }
                if ($emailTo !== '') {
                    $normalizedBlock['data']['email_to'] = substr($emailTo, 0, 190);
                }
            } elseif ($type === 'map') {
                $embedUrl = trim((string)($data['embed_url'] ?? ''));
                if (!$this->isAllowedLink($embedUrl)) {
                    $error = 'map block #' . ($index + 1) . ' vereist geldige embed_url';
                    return false;
                }
                $normalizedBlock['data']['embed_url'] = substr($embedUrl, 0, 255);
                $normalizedBlock['data']['title'] = substr(trim((string)($data['title'] ?? 'Onze locatie')), 0, 180);
            }

            $normalizedBlocks[] = $normalizedBlock;
        }

        $encoded = json_encode($normalizedBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $error = 'kon builder schema niet serialiseren';
            return false;
        }

        $normalizedJson = $encoded;
        return true;
    }

    public function getTypeConfigurations()
    {
        return [
            'basic_page' => [
                'label' => 'Basic page',
                'default_template' => 'default',
            ],
            'landing_page' => [
                'label' => 'Landing page',
                'default_template' => 'landing',
            ],
            'contact_page' => [
                'label' => 'Contact page',
                'default_template' => 'contact',
            ],
        ];
    }

    public function listAll($search = '', $status = '', $page = 1, $perPage = 20)
    {
        if (!$this->tableExists($this->table)) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'pages' => 1,
            ];
        }

        $page = max(1, (int)$page);
        $perPage = max(5, min((int)$perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        $search = trim((string)$search);
        if ($search !== '') {
            $where[] = '(title LIKE ? OR slug LIKE ? OR excerpt LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = trim((string)$status);
        if ($status !== '' && in_array($status, ['draft', 'published', 'archived'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $stmt = $this->link->prepare("SELECT COUNT(*) FROM {$this->table} {$whereSql}");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT * FROM {$this->table} {$whereSql} ORDER BY updated_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ];
        } catch (Throwable $e) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => 1,
            ];
        }
    }

    public function get($id)
    {
        if (!$this->tableExists($this->table)) {
            return null;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function findPublishedBySlug($slug)
    {
        if (!$this->tableExists($this->table)) {
            return null;
        }

        $slug = $this->slugify($slug);
        if ($slug === '') {
            return null;
        }

        try {
            $stmt = $this->link->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE slug = ?
                   AND status = 'published'
                   AND (published_at IS NULL OR published_at <= NOW())
                 LIMIT 1"
            );
            $stmt->execute([$slug]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function create(array $data, $authorId = null)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Pages-tabel ontbreekt. Draai migratie 006_pages.sql.');
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return $this->setLastError('Titel is verplicht.');
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = $this->uniqueSlug($slugInput !== '' ? $slugInput : $title);
        $status = $this->normalizeStatus($data['status'] ?? 'draft');
        $publishedAt = $this->normalizeDateTime($data['published_at'] ?? '');
        $pageType = $this->normalizePageType($data['page_type'] ?? 'basic_page');
        $template = $this->normalizeTemplate($data['template'] ?? '', $pageType);
        $templatePreset = $this->normalizeTemplatePreset($data['template_preset'] ?? '');
        $templatePayloadJson = $this->normalizeTemplatePayloadJson($data['template_payload_json'] ?? null);
        $builderJson = null;

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        try {
            $columns = [
                'title', 'slug', 'excerpt', 'content', 'builder_json', 'meta_title', 'meta_description',
                'status', 'published_at', 'created_by', 'updated_by', 'created_at', 'updated_at',
            ];
            $values = [
                $title,
                $slug,
                trim((string)($data['excerpt'] ?? '')) ?: null,
                trim((string)($data['content'] ?? '')) ?: null,
                $builderJson,
                trim((string)($data['meta_title'] ?? '')) ?: null,
                trim((string)($data['meta_description'] ?? '')) ?: null,
                $status,
                $publishedAt,
                $authorId ? (int)$authorId : null,
                $authorId ? (int)$authorId : null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ];

            if ($this->columnExists($this->table, 'template')) {
                array_splice($columns, 5, 0, ['template']);
                array_splice($values, 5, 0, [$template]);
            }
            if ($this->columnExists($this->table, 'page_type')) {
                $insertAt = $this->columnExists($this->table, 'template') ? 6 : 5;
                array_splice($columns, $insertAt, 0, ['page_type']);
                array_splice($values, $insertAt, 0, [$pageType]);
            }
            if ($this->columnExists($this->table, 'template_preset')) {
                $columns[] = 'template_preset';
                $values[] = $templatePreset;
            }
            if ($this->columnExists($this->table, 'template_payload_json')) {
                $columns[] = 'template_payload_json';
                $values[] = $templatePayloadJson;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnsSql = '`' . implode('`, `', $columns) . '`';

            $stmt = $this->link->prepare(
                "INSERT INTO {$this->table} ({$columnsSql}) VALUES ({$placeholders})"
            );

            $ok = $stmt->execute($values);

            return $ok ? (int)$this->link->lastInsertId() : false;
        } catch (Throwable $e) {
            return $this->setLastError('Pagina kon niet worden aangemaakt: ' . $e->getMessage());
        }
    }

    public function update($id, array $data, $authorId = null)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Pages-tabel ontbreekt. Draai migratie 006_pages.sql.');
        }

        $id = (int)$id;
        if ($id <= 0) {
            return $this->setLastError('Ongeldige pagina.');
        }

        $existing = $this->get($id);
        if (!$existing) {
            return $this->setLastError('Pagina niet gevonden.');
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return $this->setLastError('Titel is verplicht.');
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = $this->uniqueSlug($slugInput !== '' ? $slugInput : $title, $id);
        $status = $this->normalizeStatus($data['status'] ?? ($existing['status'] ?? 'draft'));
        $publishedAt = $this->normalizeDateTime($data['published_at'] ?? '');
        $pageType = $this->normalizePageType($data['page_type'] ?? ($existing['page_type'] ?? 'basic_page'));
        $template = $this->normalizeTemplate($data['template'] ?? ($existing['template'] ?? ''), $pageType);
        $templatePreset = $this->normalizeTemplatePreset($data['template_preset'] ?? ($existing['template_preset'] ?? ''));
        $templatePayloadJson = $this->normalizeTemplatePayloadJson($data['template_payload_json'] ?? ($existing['template_payload_json'] ?? null));
        $builderJson = null;

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = $existing['published_at'] ?: date('Y-m-d H:i:s');
        }

        try {
            $setParts = [
                'title = ?',
                'slug = ?',
                'excerpt = ?',
                'content = ?',
                'builder_json = ?',
            ];
            $params = [
                $title,
                $slug,
                trim((string)($data['excerpt'] ?? '')) ?: null,
                trim((string)($data['content'] ?? '')) ?: null,
                $builderJson,
            ];

            if ($this->columnExists($this->table, 'template')) {
                $setParts[] = 'template = ?';
                $params[] = $template;
            }
            if ($this->columnExists($this->table, 'page_type')) {
                $setParts[] = 'page_type = ?';
                $params[] = $pageType;
            }
            if ($this->columnExists($this->table, 'template_preset')) {
                $setParts[] = 'template_preset = ?';
                $params[] = $templatePreset;
            }
            if ($this->columnExists($this->table, 'template_payload_json')) {
                $setParts[] = 'template_payload_json = ?';
                $params[] = $templatePayloadJson;
            }

            $setParts = array_merge($setParts, [
                'meta_title = ?',
                'meta_description = ?',
                'status = ?',
                'published_at = ?',
                'updated_by = ?',
                'updated_at = NOW()',
            ]);

            $params = array_merge($params, [
                trim((string)($data['meta_title'] ?? '')) ?: null,
                trim((string)($data['meta_description'] ?? '')) ?: null,
                $status,
                $publishedAt,
                $authorId ? (int)$authorId : null,
                $id,
            ]);

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . ' WHERE id = ?';
            $stmt = $this->link->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $e) {
            return $this->setLastError('Pagina kon niet worden bijgewerkt: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->clearLastError();

        if (!$this->tableExists($this->table)) {
            return $this->setLastError('Pages-tabel ontbreekt. Draai migratie 006_pages.sql.');
        }

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        try {
            $stmt = $this->link->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return $this->setLastError('Pagina kon niet worden verwijderd: ' . $e->getMessage());
        }
    }
}