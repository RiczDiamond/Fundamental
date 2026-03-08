<?php
$isEdit = ($blogMode ?? 'create') === 'edit';
$formAction = $isEdit ? 'blog_update' : 'blog_create';
$formTitle = $isEdit ? 'Blogpost bewerken' : 'Nieuwe blogpost';
$previewLink = !empty($createdPreviewToken) ? (BASE_URL . '/blog/preview/' . $createdPreviewToken) : '';
?>

<div class="card">
    <div class="row" style="justify-content: space-between; align-items:center;">
        <h2 style="margin:0;"><?php echo $formTitle; ?></h2>
        <a href="/dashboard/blogs">Terug naar lijst</a>
    </div>
    <p class="muted">Eenvoudige blog-editor zonder block builder.</p>
</div>

<div class="card">
    <form method="POST" action="/dashboard/blogs" id="blog-editor-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="<?php echo $formAction; ?>">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="id" value="<?php echo (int)($blogFormData['id'] ?? 0); ?>">
        <?php endif; ?>

        <div class="row" style="margin-bottom:10px;">
            <input type="text" name="title" value="<?php echo htmlspecialchars($blogFormData['title'] ?? ''); ?>" placeholder="Titel" required>
            <input type="text" name="slug" value="<?php echo htmlspecialchars($blogFormData['slug'] ?? ''); ?>" placeholder="Slug (optioneel)">
            <input type="text" name="category" value="<?php echo htmlspecialchars($blogFormData['category'] ?? ''); ?>" placeholder="Categorie">
            <input type="text" name="tags" value="<?php echo htmlspecialchars($blogFormData['tags'] ?? ''); ?>" placeholder="Tags (comma separated)">
            <select name="status">
                <?php foreach (['draft','published','scheduled','archived'] as $blogStatus) : ?>
                    <option value="<?php echo $blogStatus; ?>" <?php echo (($blogFormData['status'] ?? 'draft') === $blogStatus) ? 'selected' : ''; ?>><?php echo $blogStatus; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="datetime-local" name="scheduled_at" value="<?php echo !empty($blogFormData['scheduled_at']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime($blogFormData['scheduled_at']))) : ''; ?>" title="Geplande publicatie">
        </div>

        <div class="two-col" style="margin-bottom:10px;">
            <div>
                <label class="small" for="blog-featured-image">Featured image</label>
                <?php
                    $mediaInputId = 'blog-featured-image';
                    $mediaInputName = 'featured_image';
                    $mediaInputValue = (string)($blogFormData['featured_image'] ?? '');
                    $mediaInputPlaceholder = 'Featured image URL';
                    $mediaInputStyle = 'width:100%;';
                    $mediaInputDisabled = false;
                    require __DIR__ . '/../partials/media-input.view.php';

                    $imagePreviewWrapId = 'blog-featured-image-preview-wrap';
                    $imagePreviewInputId = 'blog-featured-image';
                    $imagePreviewImgId = 'blog-featured-image-preview';
                    $imagePreviewAlt = 'Featured image preview';
                    require __DIR__ . '/../partials/image-preview.view.php';
                ?>
            </div>
            <div>
                <label class="small" for="blog-og-image">OG image</label>
                <?php
                    $mediaInputId = 'blog-og-image';
                    $mediaInputName = 'og_image';
                    $mediaInputValue = (string)($blogFormData['og_image'] ?? '');
                    $mediaInputPlaceholder = 'OG image URL';
                    $mediaInputStyle = 'width:100%;';
                    $mediaInputDisabled = false;
                    require __DIR__ . '/../partials/media-input.view.php';

                    $imagePreviewWrapId = 'blog-og-image-preview-wrap';
                    $imagePreviewInputId = 'blog-og-image';
                    $imagePreviewImgId = 'blog-og-image-preview';
                    $imagePreviewAlt = 'OG image preview';
                    require __DIR__ . '/../partials/image-preview.view.php';
                ?>
            </div>
        </div>

        <div class="row" style="margin-bottom:10px;">
            <input type="text" name="meta_title" value="<?php echo htmlspecialchars($blogFormData['meta_title'] ?? ''); ?>" placeholder="Meta title" style="flex:1; min-width:280px;">
            <input type="text" name="meta_description" value="<?php echo htmlspecialchars($blogFormData['meta_description'] ?? ''); ?>" placeholder="Meta description" style="flex:1; min-width:280px;">
        </div>

        <div style="margin-bottom:10px;">
            <textarea class="js-wysiwyg" name="intro" rows="2" style="width:100%;" placeholder="Intro / samenvatting"><?php echo htmlspecialchars($blogFormData['intro'] ?? ''); ?></textarea>
        </div>

        <div style="margin-bottom:10px;">
            <textarea class="js-wysiwyg" name="excerpt" rows="3" style="width:100%;" placeholder="Korte samenvatting"><?php echo htmlspecialchars($blogFormData['excerpt'] ?? ''); ?></textarea>
        </div>

        <div style="margin-bottom:10px;">
            <textarea class="js-wysiwyg" name="content" rows="14" style="width:100%;" placeholder="Content"><?php echo htmlspecialchars($blogFormData['content'] ?? ''); ?></textarea>
        </div>

        <div class="row" style="justify-content:flex-end;">
            <a href="/dashboard/blogs">Annuleren</a>
            <?php if ($isEdit) : ?>
                <button type="button" class="secondary" id="manual-autosave">Autosave nu</button>
            <?php endif; ?>
            <button type="submit"><?php echo $isEdit ? 'Opslaan' : 'Aanmaken'; ?></button>
        </div>
        <?php if ($isEdit) : ?>
            <div class="small" id="autosave-status" style="margin-top:8px;">
                Laatste autosave:
                <?php echo !empty($blogAutosave['created_at']) ? htmlspecialchars($blogAutosave['created_at']) : 'nog geen'; ?>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($isEdit) : ?>
    <div class="two-col">
        <div class="card">
            <h3 style="margin-top:0;">Preview-flow per rol</h3>
            <form method="POST" action="/dashboard/blogs" class="row">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="blog_preview_token">
                <input type="hidden" name="blog_id" value="<?php echo (int)($blogFormData['id'] ?? 0); ?>">
                <select name="required_role">
                    <option value="all">Iedereen met link</option>
                    <option value="user">Alleen user</option>
                    <option value="editor">Alleen editor</option>
                    <option value="admin">Alleen admin</option>
                </select>
                <select name="ttl_hours">
                    <option value="1">1 uur</option>
                    <option value="6">6 uur</option>
                    <option value="24" selected>24 uur</option>
                    <option value="72">72 uur</option>
                </select>
                <button type="submit" class="secondary">Maak preview-link</button>
            </form>
            <?php if ($previewLink !== '') : ?>
                <p class="small" style="margin-top:8px;">Nieuwe preview: <a href="<?php echo htmlspecialchars($previewLink); ?>" target="_blank"><?php echo htmlspecialchars($previewLink); ?></a></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Versiegeschiedenis</h3>
            <?php if (empty($blogRevisions)) : ?>
                <p class="small">Nog geen revisies.</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr><th>Wanneer</th><th>Status</th><th>Editor</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blogRevisions as $revision) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($revision['created_at'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($revision['status'] ?? 'draft'); ?></td>
                                <td><?php echo htmlspecialchars($revision['editor_name'] ?? 'onbekend'); ?></td>
                                <td>
                                    <form method="POST" action="/dashboard/blogs" class="row">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="blog_restore_revision">
                                        <input type="hidden" name="blog_id" value="<?php echo (int)($blogFormData['id'] ?? 0); ?>">
                                        <input type="hidden" name="revision_id" value="<?php echo (int)$revision['id']; ?>">
                                        <button type="submit" class="secondary">Herstel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    (function () {
        var form = document.getElementById('blog-editor-form');
        if (!form) return;

        var autosaveButton = document.getElementById('manual-autosave');
        var statusEl = document.getElementById('autosave-status');
        var idInput = form.querySelector('input[name="id"]');

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

        if (!idInput || !idInput.value) return;

        function runAutosave() {
            if (window.FundamentalEditor) {
                window.FundamentalEditor.syncForm(form);
            }
            var data = new FormData(form);
            data.set('action', 'blog_autosave');

            fetch('/dashboard/blogs', {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!statusEl) return;
                if (payload && payload.ok) {
                    statusEl.textContent = 'Laatste autosave: ' + payload.saved_at;
                }
            })
            .catch(function () {});
        }

        if (autosaveButton) {
            autosaveButton.addEventListener('click', function () {
                runAutosave();
            });
        }

        setInterval(runAutosave, 45000);
    })();
</script>
