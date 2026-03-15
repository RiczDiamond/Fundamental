<?php
    // Users overview table
?>

<div class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
        <h1 class="section-title" style="margin:0;">Gebruikers</h1>
        <div class="page-title-actions">
            <button id="users-add" class="page-title-action btn-primary">Nieuwe gebruiker</button>
        </div>
    </div>

    <div id="users-alert" class="alert" style="display:none;"></div>

    <div class="form-inline">
        <input id="users-search" type="text" placeholder="Zoeken..." />
        <select id="users-filter-role">
            <option value="">Alle rollen</option>
            <option value="user">Gebruiker</option>
            <option value="editor">Editor</option>
            <option value="admin">Admin</option>
        </select>
        <select id="users-filter-status">
            <option value="">Alle statussen</option>
            <option value="0">Actief</option>
            <option value="1">Geblokkeerd</option>
            <option value="2">Verwijderd</option>
        </select>
        <select id="users-per-page">
            <option value="10">10 per pagina</option>
            <option value="25" selected>25 per pagina</option>
            <option value="50">50 per pagina</option>
            <option value="100">100 per pagina</option>
        </select>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div style="display:flex; gap:8px; align-items:center;">
            <label style="font-size:12px; color:rgba(0,0,0,0.6);">Bulk acties</label>
            <select id="users-bulk-action" style="padding:8px; border:1px solid rgba(0,0,0,0.15); border-radius:4px;">
                <option value="">Selecteer actie</option>
                <option value="bulk_delete">Verwijder</option>
                <option value="bulk_ban">Blokkeer</option>
                <option value="bulk_unban">Deblokkeer</option>
            </select>
            <button id="users-bulk-apply" class="btn-secondary" style="padding:8px 12px;">Toepassen</button>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <input id="users-search" type="text" placeholder="Zoeken..." />
            <select id="users-filter-role">
                <option value="">Alle rollen</option>
                <option value="user">Gebruiker</option>
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>
            <select id="users-filter-status">
                <option value="">Alle statussen</option>
                <option value="0">Actief</option>
                <option value="1">Geblokkeerd</option>
                <option value="2">Verwijderd</option>
            </select>
            <select id="users-per-page">
                <option value="10">10 per pagina</option>
                <option value="25" selected>25 per pagina</option>
                <option value="50">50 per pagina</option>
                <option value="100">100 per pagina</option>
            </select>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table id="users-table" class="list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th style="width:30px;"><input id="users-select-all" type="checkbox" /></th>
                    <th>ID</th>
                    <th>Naam</th>
                    <th>E-mail</th>
                    <th>Gebruikersnaam</th>
                    <th>Rol</th>
                    <th>Status</th>
                    <th>Geregistreerd</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                <tr><td colspan="9" style="padding:16px; text-align:center; color:#888;">Laden...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="users-pagination" style="display:flex; justify-content:center; gap:8px; padding:12px 0;"></div>
</div>

<!-- User modal -->
<div id="users-modal" class="modal">
    <div class="modal-content">
        <h3 id="users-modal-title">Nieuwe gebruiker</h3>
        <div id="users-modal-alert" class="alert" style="display:none;"></div>
        <div class="modal-fields">
            <div class="modal-row">
                <label>Naam</label>
                <input id="users-modal-name" type="text" autocomplete="name" />
            </div>
            <div class="modal-row">
                <label>E-mail</label>
                <input id="users-modal-email" type="email" autocomplete="email" />
            </div>
            <div class="modal-row">
                <label>Gebruikersnaam</label>
                <input id="users-modal-login" type="text" autocomplete="username" />
            </div>
            <div class="modal-row">
                <label>Rol</label>
                <select id="users-modal-role">
                    <option value="user">Gebruiker</option>
                    <option value="editor">Editor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button id="users-modal-cancel" class="btn-secondary">Annuleren</button>
            <button id="users-modal-save" class="btn-primary">Opslaan</button>
        </div>
    </div>
</div>

