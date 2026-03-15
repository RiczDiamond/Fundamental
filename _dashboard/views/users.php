<?php
    // Users overview table
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 class="section-title">Gebruikers</h2>
        <button id="users-add" class="btn-primary" style="height:36px;">Nieuwe gebruiker</button>
    </div>

    <div id="users-alert" class="alert" style="display:none;"></div>

    <div style="overflow-x:auto;">
        <table id="users-table" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">ID</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Naam</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">E-mail</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Gebruikersnaam</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Status</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Geregistreerd</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Acties</th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                <tr><td colspan="7" style="padding:16px; text-align:center; color:#888;">Laden...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- User modal -->
<div id="users-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:#fff; border-radius:14px; padding:24px; width:360px; max-width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
        <h3 id="users-modal-title" style="margin-top:0;">Nieuwe gebruiker</h3>
        <div id="users-modal-alert" class="alert" style="display:none;"></div>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <label>Naam (display name)
                <input id="users-modal-name" type="text" autocomplete="name" />
            </label>
            <label>E-mail
                <input id="users-modal-email" type="email" autocomplete="email" />
            </label>
            <label>Gebruikersnaam
                <input id="users-modal-login" type="text" autocomplete="username" />
            </label>
            <label>Wachtwoord
                <input id="users-modal-password" type="password" autocomplete="new-password" />
            </label>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
            <button id="users-modal-cancel" class="btn-secondary">Annuleren</button>
            <button id="users-modal-save" class="btn-primary">Opslaan</button>
        </div>
    </div>
</div>

