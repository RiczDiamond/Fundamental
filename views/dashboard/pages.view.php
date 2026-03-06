<div class="card">
    <div class="row" style="justify-content: space-between;">
        <h2 style="margin:0;">Pages</h2>
        <?php if ($canPagesWrite) : ?>
            <span class="badge active">write toegestaan</span>
        <?php else : ?>
            <span class="badge banned">alleen read</span>
        <?php endif; ?>
    </div>
    <p class="muted">Pages management view staat klaar. Je kunt hier later CRUD op een pages-tabel koppelen.</p>
</div>

<div class="two-col">
    <div class="card">
        <h3>Nieuwe pagina</h3>
        <div class="small">MVP formulier (nog niet gekoppeld aan database)</div>
        <div class="row" style="margin-top:10px;">
            <input type="text" placeholder="Titel" disabled>
            <input type="text" placeholder="Slug" disabled>
            <select disabled><option>public</option><option>private</option></select>
        </div>
        <textarea rows="6" style="width:100%; margin-top:10px;" placeholder="Content" disabled></textarea>
    </div>

    <div class="card">
        <h3>Page acties</h3>
        <ul>
            <li>Navigatie beheren</li>
            <li>Versies</li>
            <li>Publicatiestatus</li>
            <li>Template toewijzing</li>
        </ul>
    </div>
</div>
