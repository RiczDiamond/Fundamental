<?php

// Role & capability management view.
?>

<div class="card" id="roles-card" data-dashboard-page="roles">
    <div class="wrap">
        <h1 class="section-title">Rollen & rechten</h1>

        <div id="roles-alert" class="alert" style="display:none;"></div>

        <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:220px;">
                <label for="roles-new-name">Nieuwe rol</label>
                <input id="roles-new-name" type="text" placeholder="rolnaam" style="width:100%;" />
            </div>
            <div style="flex:1; min-width:220px;">
                <label for="roles-new-cap">Toevoegen recht</label>
                <input id="roles-new-cap" type="text" placeholder="bijv. view_audit_log" style="width:100%;" />
            </div>
            <div style="flex:0 0 auto; display:flex; gap:8px;">
                <button id="roles-add" class="btn-primary" style="height:40px;">Rol toevoegen</button>
                <button id="roles-save" class="btn-primary" style="height:40px;">Opslaan</button>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table id="roles-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:10px; border-bottom:1px solid #ddd;">Rol</th>
                        <th style="padding:10px; border-bottom:1px solid #ddd;">Capabilities</th>
                        <th style="padding:10px; border-bottom:1px solid #ddd;">Acties</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
