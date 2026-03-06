<div class="card">
    <div class="row" style="justify-content: space-between;">
        <h2 style="margin:0;">Blogs</h2>
        <?php if ($canBlogWrite) : ?>
            <span class="badge active">write toegestaan</span>
        <?php else : ?>
            <span class="badge banned">alleen read</span>
        <?php endif; ?>
    </div>
    <p class="muted">Blog management view staat klaar. Je kunt hier later CRUD op een blog-tabel koppelen.</p>
</div>

<div class="two-col">
    <div class="card">
        <h3>Nieuwe blogpost</h3>
        <div class="small">MVP formulier (nog niet gekoppeld aan database)</div>
        <div class="row" style="margin-top:10px;">
            <input type="text" placeholder="Titel" disabled>
            <input type="text" placeholder="Slug" disabled>
            <select disabled><option>draft</option><option>published</option></select>
        </div>
        <textarea rows="6" style="width:100%; margin-top:10px;" placeholder="Content" disabled></textarea>
    </div>

    <div class="card">
        <h3>Blog acties</h3>
        <ul>
            <li>Drafts bekijken</li>
            <li>Publiceren</li>
            <li>Archiveren</li>
            <li>Zoeken op titel/slug</li>
        </ul>
    </div>
</div>
