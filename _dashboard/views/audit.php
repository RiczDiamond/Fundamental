<?php

// Audit log view.
?>

<div class="card" id="audit-card" data-dashboard-page="audit">
    <div class="wrap">
        <h1 class="section-title">Audit log</h1>

        <div id="audit-alert" class="alert" style="display:none;"></div>

        <div class="form-table" style="margin-bottom: 18px;">
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                <div style="flex:1; min-width:180px;">
                    <label for="audit-filter-action">Actie</label>
                    <input id="audit-filter-action" type="text" placeholder="Bijv. login, password_changed" style="width:100%;" />
                </div>
                <div style="flex:1; min-width:180px;">
                    <label for="audit-filter-actor">Actor (ID)</label>
                    <input id="audit-filter-actor" type="number" placeholder="User ID" style="width:100%;" />
                </div>
                <div style="flex:1; min-width:180px;">
                    <label for="audit-filter-target">Target (ID)</label>
                    <input id="audit-filter-target" type="number" placeholder="User ID" style="width:100%;" />
                </div>
                <div style="flex:1; min-width:180px;">
                    <label for="audit-filter-since">Vanaf</label>
                    <input id="audit-filter-since" type="date" style="width:100%;" />
                </div>
                <div style="flex:1; min-width:180px;">
                    <label for="audit-filter-until">Tot</label>
                    <input id="audit-filter-until" type="date" style="width:100%;" />
                </div>
                <div style="flex:0 0 180px; display:flex; gap:8px;">
                    <button id="audit-refresh" class="btn-primary" style="flex:1;">Ververs</button>
                    <button id="audit-export" class="btn-secondary" style="flex:1;">Export CSV</button>
                </div>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table id="audit-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Tijd</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Actie</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Actor</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Target</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Context</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div id="audit-pagination" style="margin-top:16px; display:flex; gap:6px; flex-wrap:wrap;"></div>
    </div>
</div>
