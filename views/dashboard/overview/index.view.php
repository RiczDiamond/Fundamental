<div class="card">
    <h2>Overzicht</h2>
    <p class="muted">Snelle status van gebruikers en permissies.</p>
    <div class="grid">
        <div class="stat"><div class="label">Users totaal</div><div class="value"><?php echo (int)($stats['users_total'] ?? 0); ?></div></div>
        <div class="stat"><div class="label">Users actief</div><div class="value"><?php echo (int)($stats['users_active'] ?? 0); ?></div></div>
        <div class="stat"><div class="label">Users banned</div><div class="value"><?php echo (int)($stats['users_banned'] ?? 0); ?></div></div>
        <div class="stat"><div class="label">Users trash</div><div class="value"><?php echo (int)($stats['users_trash'] ?? 0); ?></div></div>
        <div class="stat"><div class="label">Actief laatste 7 dagen</div><div class="value"><?php echo (int)($stats['users_active_week'] ?? 0); ?></div></div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <h3>Snelle acties</h3>
        <p><a href="/dashboard/users">Users beheren</a></p>
        <p><a href="/dashboard/profile">Profiel aanpassen</a></p>
        <p><a href="/dashboard/blogs">Blogs beheren</a></p>
        <p><a href="/dashboard/pages">Pages beheren</a></p>
        <p><a href="/dashboard/media">Media beheren</a></p>
    </div>

    <div class="card">
        <h3>Mijn rechten</h3>
        <?php if (!empty($profilePermissions)) : ?>
            <div class="row">
                <?php foreach ($profilePermissions as $permission) : ?>
                    <span class="badge active"><?php echo htmlspecialchars($permission); ?></span>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="muted">Geen rechten gevonden.</p>
        <?php endif; ?>
    </div>
</div>
