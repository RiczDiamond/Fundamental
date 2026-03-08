<?php
$statusValue = $editPageData['status'] ?? 'draft';
$pageTypeValue = $editPageData['page_type'] ?? 'basic_page';
$templateValue = $editPageData['template'] ?? 'default';
$isEditMode = !empty($editPageData);
$templatePayloadValue = [];
if (!empty($editPageData['template_payload_json'])) {
    $decodedTemplatePayload = json_decode((string)$editPageData['template_payload_json'], true);
    if (is_array($decodedTemplatePayload)) {
        $templatePayloadValue = $decodedTemplatePayload;
    }
}

$heroPayload = (isset($templatePayloadValue['hero']) && is_array($templatePayloadValue['hero']))
    ? $templatePayloadValue['hero']
    : [];

$heroTitleValue = (string)($heroPayload['title'] ?? '');
$heroSubtitleValue = (string)($heroPayload['subtitle'] ?? '');
$heroCtaLabelValue = (string)($heroPayload['cta_label'] ?? '');
$heroCtaUrlValue = (string)($heroPayload['cta_url'] ?? '');

$formAction = $isEditMode
    ? '/dashboard/pages/edit/' . (int)($editPageData['id'] ?? 0)
    : '/dashboard/pages/create';
?>
<style>
    .page-editor {
        display: grid;
        gap: 12px;
    }
    .page-editor .section {
        border: 1px solid #dcdcde;
        border-radius: 6px;
        padding: 12px;
        background: #f6f7f7;
    }
    .page-editor h3 {
        margin: 0 0 8px;
        font-size: 15px;
    }
    .page-editor .section .small {
        margin-bottom: 8px;
    }
    .page-editor-grid-2 {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .page-editor-grid-4 {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .page-editor-field {
        display: grid;
        gap: 4px;
    }
    .page-editor-field label {
        font-size: 12px;
        color: #50575e;
    }
    .classic-editor {
        border: 1px solid #8c8f94;
        border-radius: 6px;
        background: #fff;
        overflow: hidden;
    }
    .classic-editor-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 8px;
        background: #f0f0f1;
        border-bottom: 1px solid #dcdcde;
    }
    .classic-editor-tabs,
    .classic-editor-tools {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .classic-editor button {
        padding: 6px 10px;
        min-width: auto;
    }
    #page-content-editor {
        width: 100%;
        min-height: 300px;
        border: 0;
        border-radius: 0;
        padding: 12px;
        resize: vertical;
        line-height: 1.5;
    }
    .page-editor-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    @media (max-width: 960px) {
        .page-editor-grid-2,
        .page-editor-grid-4 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" id="page-editor-form" class="page-editor">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="<?php echo $isEditMode ? 'page_update' : 'page_create'; ?>">
        <?php if ($isEditMode) : ?>
            <input type="hidden" name="id" value="<?php echo (int)$editPageData['id']; ?>">
        <?php endif; ?>

        <div class="row" style="justify-content:space-between; align-items:center;">
            <p class="muted" style="margin:0;">Classic editor voor pagina-opbouw zonder block builder.</p>
            <a href="/dashboard/pages">Terug naar overzicht</a>
        </div>

        <section class="section">
            <h3>Basis</h3>
            <div class="page-editor-grid-2">
                <div class="page-editor-field">
                    <label for="page-title">Titel</label>
                    <input id="page-title" type="text" name="title" required value="<?php echo htmlspecialchars($editPageData['title'] ?? ''); ?>">
                </div>
                <div class="page-editor-field">
                    <label for="page-slug">Slug</label>
                    <input id="page-slug" type="text" name="slug" placeholder="bijv. home of contact" value="<?php echo htmlspecialchars($editPageData['slug'] ?? ''); ?>">
                </div>
            </div>
            <div class="page-editor-grid-4" style="margin-top:10px;">
                <div class="page-editor-field">
                    <label for="page-status">Status</label>
                    <select id="page-status" name="status">
                        <option value="draft" <?php echo $statusValue === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $statusValue === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $statusValue === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="page-editor-field">
                    <label for="page-type-select">Page type</label>
                    <select name="page_type" id="page-type-select">
                        <option value="basic_page" <?php echo $pageTypeValue === 'basic_page' ? 'selected' : ''; ?>>basic_page</option>
                        <option value="landing_page" <?php echo $pageTypeValue === 'landing_page' ? 'selected' : ''; ?>>landing_page</option>
                        <option value="contact_page" <?php echo $pageTypeValue === 'contact_page' ? 'selected' : ''; ?>>contact_page</option>
                    </select>
                </div>
                <div class="page-editor-field">
                    <label for="page-template-select">Template</label>
                    <select name="template" id="page-template-select">
                        <option value="default" <?php echo $templateValue === 'default' ? 'selected' : ''; ?>>default</option>
                        <option value="landing" <?php echo $templateValue === 'landing' ? 'selected' : ''; ?>>landing</option>
                        <option value="contact" <?php echo $templateValue === 'contact' ? 'selected' : ''; ?>>contact</option>
                    </select>
                </div>
                <div class="page-editor-field">
                    <label for="page-published-at">Publicatiedatum</label>
                    <input id="page-published-at" type="datetime-local" name="published_at" value="<?php echo !empty($editPageData['published_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($editPageData['published_at']))) : ''; ?>">
                </div>
            </div>
        </section>

        <section class="section">
            <h3>Hero</h3>
            <p class="small">Elke pagina kan eigen hero-tekst krijgen.</p>
            <div class="page-editor-grid-2">
                <div class="page-editor-field">
                    <label for="hero-title">Hero titel</label>
                    <input id="hero-title" type="text" name="hero_title" value="<?php echo htmlspecialchars($heroTitleValue); ?>">
                </div>
                <div class="page-editor-field">
                    <label for="hero-subtitle">Hero subtitel</label>
                    <input id="hero-subtitle" type="text" name="hero_subtitle" value="<?php echo htmlspecialchars($heroSubtitleValue); ?>">
                </div>
                <div class="page-editor-field">
                    <label for="hero-cta-label">Hero CTA label</label>
                    <input id="hero-cta-label" type="text" name="hero_cta_label" placeholder="optioneel" value="<?php echo htmlspecialchars($heroCtaLabelValue); ?>">
                </div>
                <div class="page-editor-field">
                    <label for="hero-cta-url">Hero CTA URL</label>
                    <input id="hero-cta-url" type="text" name="hero_cta_url" placeholder="optioneel" value="<?php echo htmlspecialchars($heroCtaUrlValue); ?>">
                </div>
            </div>
        </section>

        <section class="section">
            <h3>Inhoud</h3>
            <div class="page-editor-field" style="margin-bottom:10px;">
                <label for="page-excerpt">Korte intro / excerpt</label>
                <textarea id="page-excerpt" name="excerpt" rows="2"><?php echo htmlspecialchars($editPageData['excerpt'] ?? ''); ?></textarea>
            </div>

            <div class="classic-editor">
                <div class="classic-editor-head">
                    <div class="classic-editor-tabs">
                        <button type="button" class="secondary" data-mode="visual">Visual</button>
                        <button type="button" class="secondary" data-mode="html">HTML</button>
                    </div>
                    <div class="classic-editor-tools" id="classic-editor-toolbar">
                        <button type="button" class="secondary" data-wrap="<strong>|</strong>" title="Bold"><strong>B</strong></button>
                        <button type="button" class="secondary" data-wrap="<em>|</em>" title="Italic"><em>I</em></button>
                        <button type="button" class="secondary" data-wrap="<h2>|</h2>" title="Heading">H2</button>
                        <button type="button" class="secondary" data-wrap="<p>|</p>" title="Paragraaf">P</button>
                        <button type="button" class="secondary" data-wrap="<ul>\n<li>|</li>\n</ul>" title="Lijst">Lijst</button>
                        <button type="button" class="secondary" data-link="1" title="Link">Link</button>
                    </div>
                </div>

                <textarea
                    id="page-content-editor"
                    name="content"
                    rows="16"
                    placeholder="Pagina content"
                ><?php echo htmlspecialchars($editPageData['content'] ?? ''); ?></textarea>
            </div>
        </section>

        <section class="section">
            <h3>SEO</h3>
            <div class="page-editor-grid-2">
                <div class="page-editor-field">
                    <label for="meta-title">Meta title</label>
                    <input id="meta-title" type="text" name="meta_title" value="<?php echo htmlspecialchars($editPageData['meta_title'] ?? ''); ?>">
                </div>
                <div class="page-editor-field">
                    <label for="meta-description">Meta description</label>
                    <input id="meta-description" type="text" name="meta_description" value="<?php echo htmlspecialchars($editPageData['meta_description'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <div class="page-editor-actions">
            <button type="submit" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>><?php echo $isEditMode ? 'Opslaan' : 'Aanmaken'; ?></button>
            <a href="/dashboard/pages">Annuleren</a>
        </div>
    </form>
</div>

<script>
    (function () {
        var pageTypeSelect = document.getElementById('page-type-select');
        var templateSelect = document.getElementById('page-template-select');
        var editor = document.getElementById('page-content-editor');
        var toolbar = document.getElementById('classic-editor-toolbar');

        if (!pageTypeSelect || !templateSelect) {
            return;
        }

        pageTypeSelect.addEventListener('change', function () {
            if (pageTypeSelect.value === 'landing_page') {
                templateSelect.value = 'landing';
                return;
            }
            if (pageTypeSelect.value === 'contact_page') {
                templateSelect.value = 'contact';
                return;
            }
            templateSelect.value = 'default';
        });

        if (!editor || !toolbar) {
            return;
        }

        function setMode(mode) {
            var visualBtn = document.querySelector('[data-mode="visual"]');
            var htmlBtn = document.querySelector('[data-mode="html"]');

            if (mode === 'visual') {
                editor.style.fontFamily = 'Arial, sans-serif';
                editor.style.background = '#ffffff';
                if (visualBtn) {
                    visualBtn.style.background = '#1f2937';
                    visualBtn.style.color = '#ffffff';
                }
                if (htmlBtn) {
                    htmlBtn.style.background = '#ffffff';
                    htmlBtn.style.color = '#111827';
                }
            } else {
                editor.style.fontFamily = 'Consolas, monospace';
                editor.style.background = '#f9fafb';
                if (htmlBtn) {
                    htmlBtn.style.background = '#1f2937';
                    htmlBtn.style.color = '#ffffff';
                }
                if (visualBtn) {
                    visualBtn.style.background = '#ffffff';
                    visualBtn.style.color = '#111827';
                }
            }
        }

        function wrapSelection(before, after) {
            var start = editor.selectionStart || 0;
            var end = editor.selectionEnd || 0;
            var value = editor.value || '';
            var selected = value.slice(start, end);
            var replacement = before + selected + after;

            editor.value = value.slice(0, start) + replacement + value.slice(end);
            editor.focus();

            var caret = start + replacement.length;
            editor.setSelectionRange(caret, caret);
        }

        document.querySelectorAll('[data-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                setMode(button.getAttribute('data-mode') || 'html');
            });
        });

        toolbar.querySelectorAll('[data-wrap]').forEach(function (button) {
            button.addEventListener('click', function () {
                var tpl = String(button.getAttribute('data-wrap') || '');
                var parts = tpl.split('|');
                var before = parts[0] || '';
                var after = parts[1] || '';
                wrapSelection(before, after);
            });
        });

        toolbar.querySelectorAll('[data-link]').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = window.prompt('URL (bijv. /contact of https://...)', '/');
                if (!url) {
                    return;
                }
                wrapSelection('<a href="' + String(url).replace(/"/g, '&quot;') + '">', '</a>');
            });
        });

        setMode('visual');
    })();
</script>
