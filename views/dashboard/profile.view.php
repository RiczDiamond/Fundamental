<div class="two-col">
    <div class="card">
        <h2>Profile</h2>
        <form method="POST" action="/dashboard/profile">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="profile_update">

            <div class="row" style="margin-bottom:8px;">
                <input type="text" name="display_name" value="<?php echo htmlspecialchars($currentUser['display_name'] ?? ''); ?>" placeholder="Display name">
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" placeholder="Voornaam">
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" placeholder="Achternaam">
            </div>

            <div class="row" style="margin-bottom:8px;">
                <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" placeholder="Gebruikersnaam" required>
                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" placeholder="Email" required>
            </div>

            <div class="row" style="margin-bottom:8px;">
                <select name="gender">
                    <option value="" <?php echo empty($currentUser['gender']) ? 'selected' : ''; ?>>Gender (niet opgegeven)</option>
                    <option value="male" <?php echo (($currentUser['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Man</option>
                    <option value="female" <?php echo (($currentUser['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Vrouw</option>
                    <option value="other" <?php echo (($currentUser['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Anders</option>
                </select>
                <input type="date" name="birth_date" value="<?php echo htmlspecialchars($currentUser['birth_date'] ?? ''); ?>" placeholder="Geboortedatum">
            </div>

            <button type="submit">Profiel opslaan</button>
        </form>

        <div class="small" style="margin-top:12px;">
            Rol: <strong><?php echo htmlspecialchars($currentUser['role'] ?? 'user'); ?></strong> |
            Status: <strong><?php echo htmlspecialchars($currentUser['status'] ?? 'active'); ?></strong> |
            Email verified: <strong><?php echo !empty($currentUser['email_verified']) ? 'ja' : 'nee'; ?></strong>
        </div>
        <div class="small" style="margin-top:4px;">
            Aangemaakt: <?php echo htmlspecialchars($currentUser['created_at'] ?? '-'); ?> |
            Laatste login: <?php echo htmlspecialchars($currentUser['last_login'] ?? '-'); ?> |
            Laatste IP: <?php echo htmlspecialchars($currentUser['last_ip'] ?? '-'); ?>
        </div>
    </div>

    <div class="card">
        <h2>Wachtwoord</h2>
        <form method="POST" action="/dashboard/profile">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="profile_password">

            <div class="row" style="margin-bottom:8px;">
                <input type="password" name="current_password" placeholder="Huidig wachtwoord" required>
            </div>
            <div class="row" style="margin-bottom:8px;">
                <input type="password" name="new_password" placeholder="Nieuw wachtwoord" required>
                <input type="password" name="new_password_confirm" placeholder="Herhaal nieuw wachtwoord" required>
            </div>
            <button type="submit" class="secondary">Wachtwoord wijzigen</button>
        </form>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <h3>Mijn groepen</h3>
        <?php if (empty($profileGroups)) : ?>
            <p class="muted">Geen groepen gevonden.</p>
        <?php else : ?>
            <ul>
                <?php foreach ($profileGroups as $group) : ?>
                    <li><strong><?php echo htmlspecialchars($group['group_name']); ?></strong> — <?php echo htmlspecialchars($group['description'] ?? ''); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Mijn permissies</h3>
        <?php if (empty($profilePermissions)) : ?>
            <p class="muted">Geen permissies gevonden.</p>
        <?php else : ?>
            <div class="row">
                <?php foreach ($profilePermissions as $permission) : ?>
                    <span class="badge active"><?php echo htmlspecialchars($permission); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
