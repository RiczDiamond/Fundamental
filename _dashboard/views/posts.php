<?php
    // Posts overview.
    $postTypes = mol_get_post_types();
    $currentType = trim((string) ($postType ?? ''));
?>
<script>
    window.MOL_POST_TYPES = <?php echo json_encode($postTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.MOL_CURRENT_POST_TYPE = <?php echo json_encode($currentType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<div class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
        <h1 class="section-title" style="margin:0;">Posts</h1>
        <div class="page-title-actions">
            <button id="posts-add" class="page-title-action btn-primary">Nieuw bericht</button>
        </div>
    </div>

    <div id="posts-alert" class="alert" style="display:none;"></div>

    <div style="overflow-x:auto;">
        <table id="posts-table" class="list-table widefat fixed striped posts" style="min-width:720px;">
            <thead id="posts-thead"></thead>
            <tbody id="posts-tbody">
                <tr><td colspan="6" style="padding:16px; text-align:center; color:#888;">Laden...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Post modal -->
<div id="posts-modal" class="modal">
    <div class="modal-content">
        <h3 id="posts-modal-title">Nieuw bericht</h3>
        <div id="posts-modal-alert" class="alert" style="display:none;"></div>
        <div class="modal-fields">
            <div class="modal-row">
                <label>Titel</label>
                <input id="posts-modal-title-input" type="text" autocomplete="off" />
            </div>
            <div class="modal-row">
                <label>Type</label>
                <select id="posts-modal-type">
                    <?php foreach ($postTypes as $key => $type): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($type['labels']['singular'] ?? $key); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-row">
                <label>Status</label>
                <select id="posts-modal-status">
                    <option value="draft">Concept</option>
                    <option value="published">Gepubliceerd</option>
                </select>
            </div>
            <div class="modal-row" id="posts-modal-row-date" style="display:none;">
                <label>Datum</label>
                <input id="posts-modal-date" type="datetime-local" />
            </div>
            <div class="modal-row" id="posts-modal-row-author" style="display:none;">
                <label>Auteur ID</label>
                <input id="posts-modal-author" type="text" />
            </div>
            <div class="modal-row" id="posts-modal-row-excerpt" style="display:none;">
                <label>Excerpt</label>
                <textarea id="posts-modal-excerpt" rows="3"></textarea>
            </div>
            <div class="modal-row" id="posts-modal-row-taxes" style="display:none;">
                <label>Taxonomieën</label>
                <div id="posts-modal-taxonomies"></div>
            </div>
            <div class="modal-row" id="posts-modal-row-meta" style="display:none;">
                <label>Custom velden (key=value)</label>
                <textarea id="posts-modal-meta" rows="3" placeholder="key=value&#10;foo=bar"></textarea>
                <p style="font-size:12px; color:rgba(0,0,0,0.6);">Scheid elke regel met een nieuwe regel; key=value.</p>
            </div>
            <div class="modal-row">
                <label>Inhoud</label>
                <div id="posts-modal-blocks" class="blocks-editor">
                    <div class="blocks-toolbar">
                        <select id="posts-modal-block-type" class="block-type-select">
                            <option value="paragraph">Paragraaf</option>
                            <option value="heading">Kop</option>
                            <option value="image">Afbeelding</option>
                        </select>
                        <button id="posts-modal-add-block" class="btn-secondary btn-small">Blok toevoegen</button>
                    </div>
                    <div id="posts-modal-block-list" class="block-list"></div>
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button id="posts-modal-cancel" class="btn-secondary">Annuleren</button>
            <button id="posts-modal-save" class="btn-primary">Opslaan</button>
        </div>
    </div>
</div>
