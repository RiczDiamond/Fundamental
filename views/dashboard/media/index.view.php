<style>
    .media-shell {
        display: grid;
        gap: 12px;
    }
    .media-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }
    .media-topbar h2,
    .media-section-title {
        margin: 0;
    }
    .media-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .media-intro {
        margin: 6px 0 0;
    }

    .media-tools-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .media-form-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .media-form-grid .span-2 {
        grid-column: span 2;
    }
    .media-folder-list {
        margin: 10px 0 0;
        padding-left: 18px;
    }

    .media-file-list {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .media-row {
        border: 1px solid #dcdcde;
        border-radius: 6px;
        padding: 10px;
        background: #fff;
    }
    .media-row-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 8px;
    }
    .media-row-title {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }
    .media-row-path {
        font-size: 12px;
        word-break: break-all;
    }
    .media-row-form {
        display: grid;
        gap: 8px;
    }
    .media-save-status {
        font-size: 12px;
        color: #646970;
        min-height: 18px;
    }
    .media-save-status.is-saving {
        color: #1d4ed8;
    }
    .media-save-status.is-saved {
        color: #166534;
    }
    .media-save-status.is-error {
        color: #b32d2e;
    }
    .media-meta-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px;
        gap: 8px;
    }
    .media-crop-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 8px;
    }
    .media-pagination {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .media-filter-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 180px 180px auto auto;
        gap: 8px;
    }

    @media (max-width: 1100px) {
        .media-tools-grid,
        .media-meta-grid {
            grid-template-columns: 1fr;
        }
        .media-crop-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .media-filter-form {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 760px) {
        .media-topbar {
            flex-direction: column;
            align-items: stretch;
        }
        .media-form-grid,
        .media-filter-form,
        .media-crop-grid {
            grid-template-columns: 1fr;
        }
        .media-form-grid .span-2 {
            grid-column: span 1;
        }
        .media-row-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="media-shell">
    <div class="card">
        <div class="media-topbar">
            <div>
                <h2>Media</h2>
                <p class="muted media-intro">Aparte mediapagina met mappen, upload en metadata-beheer.</p>
            </div>
            <div class="media-actions">
                <?php if ($canPagesWrite) : ?>
                    <span class="badge active">write toegestaan</span>
                <?php else : ?>
                    <span class="badge banned">alleen read</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="media-tools-grid">
        <div class="card">
            <h3 class="media-section-title">Mappen</h3>
            <form method="POST" action="/dashboard/media" class="media-form-grid">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="media_folder_create">

                <input type="text" name="name" placeholder="Mapnaam" <?php echo !$canPagesWrite ? 'disabled' : ''; ?> required>
                <select name="parent_id" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                    <option value="0">Geen parent</option>
                    <?php foreach (($mediaFolders ?? []) as $folder) : ?>
                        <option value="<?php echo (int)$folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="secondary span-2" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Map toevoegen</button>
            </form>

            <ul class="media-folder-list">
                <?php if (empty($mediaFolders)) : ?>
                    <li class="small">Nog geen mappen.</li>
                <?php else : ?>
                    <?php foreach ($mediaFolders as $folder) : ?>
                        <li><?php echo htmlspecialchars($folder['name']); ?> <span class="small">(#<?php echo (int)$folder['id']; ?>)</span></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <h3 class="media-section-title">Upload</h3>
            <form method="POST" action="/dashboard/media" enctype="multipart/form-data" class="media-form-grid">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="media_upload">

                <input type="file" name="media_file" <?php echo !$canPagesWrite ? 'disabled' : ''; ?> required>
                <select name="folder_id" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                    <option value="0">Geen map</option>
                    <?php foreach (($mediaFolders ?? []) as $folder) : ?>
                        <option value="<?php echo (int)$folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <input class="span-2" type="text" name="alt_text" placeholder="Alt-tekst" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>

                <button type="submit" class="span-2" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Upload</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3 class="media-section-title">Bestanden</h3>

        <form method="GET" action="/dashboard/media" class="media-filter-form" style="margin-top:10px;">
            <input type="text" name="media_q" value="<?php echo htmlspecialchars($mediaSearch ?? ''); ?>" placeholder="Zoek bestandsnaam of alt-tekst">
            <select name="media_folder">
                <option value="0">Alle mappen</option>
                <?php foreach (($mediaFolders ?? []) as $folder) : ?>
                    <option value="<?php echo (int)$folder['id']; ?>" <?php echo ((int)($mediaFolderFilter ?? 0) === (int)$folder['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($folder['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="media_sort">
                <option value="newest" <?php echo (($mediaSort ?? 'newest') === 'newest') ? 'selected' : ''; ?>>Nieuwste</option>
                <option value="oldest" <?php echo (($mediaSort ?? '') === 'oldest') ? 'selected' : ''; ?>>Oudste</option>
                <option value="name_asc" <?php echo (($mediaSort ?? '') === 'name_asc') ? 'selected' : ''; ?>>Naam A-Z</option>
                <option value="name_desc" <?php echo (($mediaSort ?? '') === 'name_desc') ? 'selected' : ''; ?>>Naam Z-A</option>
            </select>
            <button type="submit" class="secondary">Filter</button>
            <a href="/dashboard/media">Reset</a>
        </form>

        <div class="small" style="margin-top:8px;">Totaal media: <?php echo (int)($mediaTotalItems ?? 0); ?></div>

        <div class="media-file-list">
            <?php if (empty($mediaItems)) : ?>
                <div class="media-row">
                    <p class="small" style="margin:0;">Nog geen media-items.</p>
                </div>
            <?php else : ?>
                <?php foreach ($mediaItems as $media) : ?>
                    <div class="media-row">
                        <div class="media-row-head">
                            <p class="media-row-title"><?php echo htmlspecialchars($media['filename'] ?? ''); ?></p>
                            <a class="media-row-path" href="<?php echo htmlspecialchars($media['path'] ?? '#'); ?>" target="_blank"><?php echo htmlspecialchars($media['path'] ?? ''); ?></a>
                        </div>

                        <form method="POST" action="/dashboard/media" class="media-row-form" data-autosave="media-meta">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="media_update">
                            <input type="hidden" name="id" value="<?php echo (int)$media['id']; ?>">

                            <div class="media-meta-grid">
                                <input type="text" name="alt_text" value="<?php echo htmlspecialchars($media['alt_text'] ?? ''); ?>" placeholder="Alt-tekst" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <select name="folder_id" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                    <option value="0">Geen map</option>
                                    <?php foreach (($mediaFolders ?? []) as $folder) : ?>
                                        <option value="<?php echo (int)$folder['id']; ?>" <?php echo ((int)($media['folder_id'] ?? 0) === (int)$folder['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($folder['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="media-crop-grid">
                                <input type="number" name="crop_x" value="<?php echo htmlspecialchars((string)($media['crop_x'] ?? '')); ?>" placeholder="crop x" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <input type="number" name="crop_y" value="<?php echo htmlspecialchars((string)($media['crop_y'] ?? '')); ?>" placeholder="crop y" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <input type="number" name="crop_w" value="<?php echo htmlspecialchars((string)($media['crop_w'] ?? '')); ?>" placeholder="crop w" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <input type="number" name="crop_h" value="<?php echo htmlspecialchars((string)($media['crop_h'] ?? '')); ?>" placeholder="crop h" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <input type="number" name="resize_w" value="<?php echo htmlspecialchars((string)($media['resize_w'] ?? '')); ?>" placeholder="resize w" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                                <input type="number" name="resize_h" value="<?php echo htmlspecialchars((string)($media['resize_h'] ?? '')); ?>" placeholder="resize h" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                            </div>

                            <div class="row">
                                <button type="submit" class="secondary" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Nu opslaan</button>
                                <span class="media-save-status" aria-live="polite"></span>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (($mediaPagesTotal ?? 1) > 1) : ?>
            <div class="media-pagination">
                <?php for ($mp = 1; $mp <= (int)$mediaPagesTotal; $mp++) : ?>
                    <?php
                        $mediaQuery = http_build_query([
                            'media_q' => $mediaSearch ?? '',
                            'media_folder' => $mediaFolderFilter ?? 0,
                            'media_sort' => $mediaSort ?? 'newest',
                            'media_per_page' => $mediaPerPage ?? 12,
                            'media_page' => $mp,
                        ]);
                    ?>
                    <a href="/dashboard/media?<?php echo htmlspecialchars($mediaQuery); ?>" class="<?php echo ((int)($mediaPage ?? 1) === $mp) ? 'badge active' : 'badge'; ?>"><?php echo $mp; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        var forms = document.querySelectorAll('form[data-autosave="media-meta"]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            var statusEl = form.querySelector('.media-save-status');
            var timer = null;
            var pending = false;

            function setStatus(text, stateClass) {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = text || '';
                statusEl.classList.remove('is-saving', 'is-saved', 'is-error');
                if (stateClass) {
                    statusEl.classList.add(stateClass);
                }
            }

            function submitAutosave() {
                if (pending) {
                    return;
                }

                pending = true;
                setStatus('Opslaan...', 'is-saving');

                var payload = new FormData(form);
                fetch('/dashboard/media', {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            return { ok: false, error: 'Onbekende server response.' };
                        }).then(function (json) {
                            return { ok: response.ok && !!json.ok, error: json.error || '' };
                        });
                    })
                    .then(function (result) {
                        if (result.ok) {
                            setStatus('Opgeslagen', 'is-saved');
                            return;
                        }
                        setStatus(result.error || 'Opslaan mislukt', 'is-error');
                    })
                    .catch(function () {
                        setStatus('Netwerkfout bij opslaan', 'is-error');
                    })
                    .finally(function () {
                        pending = false;
                    });
            }

            function scheduleAutosave() {
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(submitAutosave, 700);
            }

            form.addEventListener('submit', function () {
                setStatus('Opslaan...', 'is-saving');
            });

            form.querySelectorAll('input[name="alt_text"], input[name^="crop_"], input[name^="resize_"]').forEach(function (field) {
                if (field.disabled) {
                    return;
                }
                field.addEventListener('input', scheduleAutosave);
                field.addEventListener('change', scheduleAutosave);
            });

            form.querySelectorAll('select[name="folder_id"]').forEach(function (field) {
                if (field.disabled) {
                    return;
                }
                field.addEventListener('change', scheduleAutosave);
            });
        });
    })();
</script>
