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
    ? '/dashboard/content/' . ($typeDefinition['slug'] ?? $typeKey) . '/edit/' . (int)($item['id'] ?? 0)
    : '/dashboard/content/' . ($typeDefinition['slug'] ?? $typeKey) . '/create';

$contentFormHeading = trim((string)($contentTypeFormHeading ?? ''));
if ($contentFormHeading === '') {
    $contentFormHeading = (string)($typeDefinition['label'] ?? 'Content');
}

$contentFormIntro = trim((string)($contentTypeFormIntro ?? ''));
if ($contentFormIntro === '') {
    $contentFormIntro = $contentFormHeading . ' beheren via aparte URL.';
}
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

        <div class="row" style="justify-content:space-between; align-items:center;">
            <p class="muted" style="margin:0;"><?php echo htmlspecialchars($contentFormIntro); ?></p>
            <a href="/dashboard/content/<?php echo htmlspecialchars($typeDefinition['slug'] ?? $typeKey); ?>">Terug naar overzicht</a>
        </div>

        <section class="section">
            <h3 style="margin:0 0 8px;">Basis</h3>
            <div class="content-editor-grid">
                <div class="content-editor-field">
                    <label for="content-title">Titel</label>
                    <input id="content-title" type="text" name="title" required value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                </div>
                <div class="content-editor-field">
                    <label for="content-slug">Slug</label>
                    <input id="content-slug" type="text" name="slug" value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                </div>
                <div class="content-editor-field">
                    <label for="content-status">Status</label>
                    <select id="content-status" name="status">
                        <option value="draft" <?php echo (($item['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo (($item['status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo (($item['status'] ?? '') === 'archived') ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="content-editor-field">
                    <label for="content-published-at">Publicatiedatum</label>
                    <input id="content-published-at" type="datetime-local" name="published_at" value="<?php echo !empty($item['published_at']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$item['published_at']))) : ''; ?>">
                </div>
                <div class="content-editor-field">
                    <label for="content-starts-at">Startdatum (events/vacatures)</label>
                    <input id="content-starts-at" type="datetime-local" name="starts_at" value="<?php echo !empty($item['starts_at']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$item['starts_at']))) : ''; ?>">
                </div>
                <div class="content-editor-field">
                    <label for="content-ends-at">Einddatum</label>
                    <input id="content-ends-at" type="datetime-local" name="ends_at" value="<?php echo !empty($item['ends_at']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$item['ends_at']))) : ''; ?>">
                </div>
            </div>
        </section>

        <section class="section">
            <h3 style="margin:0 0 8px;">Content</h3>
            <div class="content-editor-field" style="margin-bottom:10px;">
                <label for="content-excerpt">Excerpt</label>
                <textarea id="content-excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($item['excerpt'] ?? ''); ?></textarea>
            </div>
            <div class="content-editor-field" style="margin-bottom:10px;">
                <label for="content-main">Inhoud</label>
                <textarea id="content-main" name="content" rows="12"><?php echo htmlspecialchars($item['content'] ?? ''); ?></textarea>
            </div>
            <div class="content-editor-field">
                <label for="content-featured-image">Featured image URL</label>
                <input id="content-featured-image" type="text" name="featured_image" value="<?php echo htmlspecialchars($item['featured_image'] ?? ''); ?>">
            </div>
        </section>

        <section class="section">
            <h3 style="margin:0 0 8px;">Type velden</h3>
            <div class="content-editor-grid">
                <?php foreach (($typeDefinition['fields'] ?? []) as $field) : ?>
                    <?php
                        $fieldName = (string)($field['name'] ?? '');
                        if ($fieldName === '') {
                            continue;
                        }
                        $fieldValue = (string)($payload[$fieldName] ?? '');
                        $inputName = 'payload_' . $fieldName;
                        $isTextarea = (($field['type'] ?? 'text') === 'textarea');
                    ?>
                    <div class="content-editor-field">
                        <label for="<?php echo htmlspecialchars($inputName); ?>"><?php echo htmlspecialchars((string)($field['label'] ?? $fieldName)); ?></label>
                        <?php if ($isTextarea) : ?>
                            <textarea id="<?php echo htmlspecialchars($inputName); ?>" name="<?php echo htmlspecialchars($inputName); ?>" rows="4"><?php echo htmlspecialchars($fieldValue); ?></textarea>
                        <?php else : ?>
                            <input id="<?php echo htmlspecialchars($inputName); ?>" type="text" name="<?php echo htmlspecialchars($inputName); ?>" value="<?php echo htmlspecialchars($fieldValue); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section">
            <h3 style="margin:0 0 8px;">SEO</h3>
            <div class="content-editor-grid">
                <div class="content-editor-field">
                    <label for="content-meta-title">Meta title</label>
                    <input id="content-meta-title" type="text" name="meta_title" value="<?php echo htmlspecialchars($item['meta_title'] ?? ''); ?>">
                </div>
                <div class="content-editor-field">
                    <label for="content-meta-description">Meta description</label>
                    <input id="content-meta-description" type="text" name="meta_description" value="<?php echo htmlspecialchars($item['meta_description'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <div class="row">
            <button type="submit" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>><?php echo $isEdit ? 'Opslaan' : 'Aanmaken'; ?></button>
            <a href="/dashboard/content/<?php echo htmlspecialchars($typeDefinition['slug'] ?? $typeKey); ?>">Annuleren</a>
        </div>
    </form>
</div>
