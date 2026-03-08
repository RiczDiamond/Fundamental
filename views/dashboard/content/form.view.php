<?php
$isEdit = !empty($contentEditItem);
$item = is_array($contentEditItem ?? null) ? $contentEditItem : [];
$typeKey = (string)($contentSelectedTypeKey ?? 'services');
$typeDefinition = is_array($contentSelectedTypeDefinition ?? null) ? $contentSelectedTypeDefinition : [];
$payload = [];
if (!empty($item['payload_json'])) {
    $decoded = json_decode((string)$item['payload_json'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
$formAction = $isEdit
    ? '/dashboard/' . ($typeDefinition['slug'] ?? $typeKey) . '/edit/' . (int)($item['id'] ?? 0)
    : '/dashboard/' . ($typeDefinition['slug'] ?? $typeKey) . '/create';
$baseDashboardPath = '/dashboard/' . ($typeDefinition['slug'] ?? $typeKey);

$contentFormHeading = trim((string)($contentTypeFormHeading ?? ''));
if ($contentFormHeading === '') {
    $contentFormHeading = (string)($typeDefinition['label'] ?? 'Content');
}

$contentFormIntro = trim((string)($contentTypeFormIntro ?? ''));
if ($contentFormIntro === '') {
    $contentFormIntro = 'Manage ' . $contentFormHeading . ' in a dedicated editor screen.';
}

$toDatetimeLocal = static function ($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\\TH:i', $ts) : '';
};

$baseFields = [
    [
        'id' => 'content-title',
        'name' => 'title',
        'label' => 'Title',
        'type' => 'text',
        'value' => (string)($item['title'] ?? ''),
        'required' => true,
    ],
    [
        'id' => 'content-slug',
        'name' => 'slug',
        'label' => 'Permalink',
        'type' => 'text',
        'value' => (string)($item['slug'] ?? ''),
        'placeholder' => 'auto-generated if empty',
    ],
    [
        'id' => 'content-status',
        'name' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'value' => (string)($item['status'] ?? 'draft'),
        'options' => [
            'draft' => 'Draft',
            'review' => 'In Review',
            'approved' => 'Approved',
            'published' => 'Published',
            'archived' => 'Archived',
        ],
    ],
    [
        'id' => 'content-published-at',
        'name' => 'published_at',
        'label' => 'Publish Date',
        'type' => 'datetime',
        'value' => $toDatetimeLocal($item['published_at'] ?? ''),
    ],
    [
        'id' => 'content-starts-at',
        'name' => 'starts_at',
        'label' => 'Start Date',
        'type' => 'datetime',
        'value' => $toDatetimeLocal($item['starts_at'] ?? ''),
    ],
    [
        'id' => 'content-ends-at',
        'name' => 'ends_at',
        'label' => 'End Date',
        'type' => 'datetime',
        'value' => $toDatetimeLocal($item['ends_at'] ?? ''),
    ],
];

$contentMainFields = [
    [
        'id' => 'content-excerpt',
        'name' => 'excerpt',
        'label' => 'Excerpt',
        'type' => 'textarea',
        'rows' => 3,
        'value' => (string)($item['excerpt'] ?? ''),
    ],
    [
        'id' => 'content-main',
        'name' => 'content',
        'label' => 'Content',
        'type' => 'textarea',
        'rows' => 12,
        'value' => (string)($item['content'] ?? ''),
    ],
    [
        'id' => 'content-featured-image',
        'name' => 'featured_image',
        'label' => 'Featured Image',
        'type' => 'media',
        'value' => (string)($item['featured_image'] ?? ''),
        'placeholder' => 'Choose or paste an image URL',
        'show_preview' => true,
    ],
];

$typeFields = [];
foreach (($typeDefinition['fields'] ?? []) as $field) {
    $fieldName = (string)($field['name'] ?? '');
    if ($fieldName === '') {
        continue;
    }
    $fieldValue = (string)($payload[$fieldName] ?? '');
    $fieldType = (string)($field['type'] ?? 'text');
    $fieldLabel = (string)($field['label'] ?? $fieldName);

    $typeFields[] = [
        'id' => 'payload_' . $fieldName,
        'name' => 'payload_' . $fieldName,
        'label' => $fieldLabel,
        'type' => $fieldType === 'textarea' ? 'textarea' : ($fieldType === 'media' ? 'media' : 'text'),
        'value' => $fieldValue,
        'rows' => 4,
        'placeholder' => $fieldLabel,
    ];
}

$seoFields = [
    [
        'id' => 'content-meta-title',
        'name' => 'meta_title',
        'label' => 'SEO Title',
        'type' => 'text',
        'value' => (string)($item['meta_title'] ?? ''),
    ],
    [
        'id' => 'content-meta-description',
        'name' => 'meta_description',
        'label' => 'Meta Description',
        'type' => 'text',
        'value' => (string)($item['meta_description'] ?? ''),
    ],
];
?>
<style>
    .content-editor { display: grid; gap: 12px; }
    .content-editor .section { border: 1px solid #dcdcde; border-radius: 6px; padding: 12px; background: #f6f7f7; }
    .content-editor-grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .content-editor-field { display: grid; gap: 4px; }
    .content-editor-field label { font-size: 12px; color: #50575e; }
    @media (max-width: 960px) {
        .content-editor-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="card">
    <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="content-editor">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'content_update' : 'content_create'; ?>">
        <input type="hidden" name="content_type" value="<?php echo htmlspecialchars($typeKey); ?>">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
        <?php endif; ?>

        <?php require __DIR__ . '/components/form-header.view.php'; ?>

        <?php
        $sectionTitle = 'Post Settings';
        $sectionFields = $baseFields;
        $sectionGrid = true;
        require __DIR__ . '/components/form-section.view.php';
        ?>

        <?php
        $sectionTitle = 'Content';
        $sectionFields = $contentMainFields;
        $sectionGrid = false;
        require __DIR__ . '/components/form-section.view.php';
        ?>

        <?php if (!empty($typeFields)) : ?>
            <?php
            $sectionTitle = 'Custom Fields';
            $sectionFields = $typeFields;
            $sectionGrid = true;
            require __DIR__ . '/components/form-section.view.php';
            ?>
        <?php endif; ?>

        <?php
        $sectionTitle = 'SEO';
        $sectionFields = $seoFields;
        $sectionGrid = true;
        require __DIR__ . '/components/form-section.view.php';
        ?>

        <?php require __DIR__ . '/components/form-actions.view.php'; ?>
    </form>
</div>

<script>
(function () {
    var form = document.querySelector('form.content-editor');
    if (!form) return;

    function initEditorBindings() {
        if (!window.FundamentalEditor) {
            return false;
        }
        form.querySelectorAll('.js-wysiwyg').forEach(function (textarea) {
            window.FundamentalEditor.initWysiwyg(textarea);
        });
        form.querySelectorAll('.js-open-media').forEach(function (button) {
            window.FundamentalEditor.bindMediaButton(button);
        });
        form.querySelectorAll('.js-image-preview').forEach(function (previewWrap) {
            window.FundamentalEditor.bindImagePreview(previewWrap);
        });
        form.addEventListener('submit', function () {
            window.FundamentalEditor.syncForm(form);
        });
        return true;
    }

    if (!initEditorBindings()) {
        document.addEventListener('DOMContentLoaded', initEditorBindings, { once: true });
    }
})();
</script>
