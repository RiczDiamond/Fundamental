<?php
    // Post types manager
    $postTypes = mol_get_post_types();
?>

<div class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
        <h1 class="section-title" style="margin:0;">Post types</h1>
        <div class="page-title-actions">
            <button id="post-types-add" class="page-title-action btn-primary">Nieuw post type</button>
        </div>
    </div>

    <div id="post-types-alert" class="alert" style="display:none;"></div>

    <div style="overflow-x:auto;">
        <table id="post-types-table" class="list-table widefat fixed striped post-types">
            <thead>
                <tr>
                    <th style="width:36px;"></th>
                    <th>Naam</th>
                    <th>Icon</th>
                    <th>Label (enkelvoud)</th>
                    <th>Label (meervoud)</th>
                    <th>Order</th>
                    <th>Publiek</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody id="post-types-tbody">
                <tr><td colspan="8" style="padding:16px; text-align:center; color:#888;">Laden...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Post type modal -->
<div id="post-types-modal" class="modal">
    <div class="modal-content">
        <h3 id="post-types-modal-title">Nieuw post type</h3>
        <div id="post-types-modal-alert" class="alert" style="display:none;"></div>
        <div class="modal-fields">
            <div class="modal-row">
                <label>Naam (slug)</label>
                <input id="post-types-modal-name" type="text" autocomplete="off" />
                <p style="font-size:12px; color:rgba(0,0,0,0.6);">Alleen letters/nummers/underscore/dash. Bijvoorbeeld: <code>event</code>.</p>
            </div>
            <div class="modal-row">
                <label>Label (enkelvoud)</label>
                <input id="post-types-modal-label-singular" type="text" autocomplete="off" />
            </div>
            <div class="modal-row">
                <label>Label (meervoud)</label>
                <input id="post-types-modal-label-plural" type="text" autocomplete="off" />
            </div>
            <div class="modal-row">
                <label>Supports</label>
                <div style="display:flex; flex-wrap:wrap; gap:12px;">
                    <label><input type="checkbox" class="post-types-support" value="title" checked /> Titel</label>
                    <label><input type="checkbox" class="post-types-support" value="content" checked /> Inhoud</label>
                    <label><input type="checkbox" class="post-types-support" value="excerpt" /> Excerpt</label>
                    <label><input type="checkbox" class="post-types-support" value="date" /> Datum</label>
                    <label><input type="checkbox" class="post-types-support" value="author" /> Auteur</label>
                    <label><input type="checkbox" class="post-types-support" value="custom-fields" /> Custom velden</label>
                </div>
            </div>
            <div class="modal-row">
                <label>Taxonomieën (komma-gescheiden)</label>
                <input id="post-types-modal-taxonomies" type="text" autocomplete="off" placeholder="category,tag" />
            </div>
            <div class="modal-row">
                <label>Menu icon</label>
                <input id="post-types-modal-icon" type="text" autocomplete="off" placeholder="Bijv. ⭐ of dashicons-admin-post" />
                <p style="font-size:12px; color:rgba(0,0,0,0.6);">Een emoji of CSS-klasse voor een icon (zie WP dashicons). Leeg is default.</p>
            </div>
            <div class="modal-row">
                <label>Menu volgorde</label>
                <input id="post-types-modal-order" type="number" min="0" step="1" value="0" />
                <p style="font-size:12px; color:rgba(0,0,0,0.6);">Kleiner = hoger in het menu.</p>
            </div>
            <div class="modal-row">
                <label>Publiek</label>
                <select id="post-types-modal-public">
                    <option value="1" selected>Ja</option>
                    <option value="0">Nee</option>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button id="post-types-modal-cancel" class="btn-secondary">Annuleren</button>
            <button id="post-types-modal-save" class="btn-primary">Opslaan</button>
        </div>
    </div>
</div>
