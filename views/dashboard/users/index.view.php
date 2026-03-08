<div class="card">
    <div class="row" style="justify-content: space-between;">
        <h2 style="margin:0;">Users</h2>
        <?php if (!$canManageUsers) : ?><span class="badge banned">Alleen-lezen: geen users.manage</span><?php endif; ?>
    </div>

    <form method="GET" action="/dashboard/users" class="row" style="margin-top:10px;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($usersSearch); ?>" placeholder="Zoek op username of email">
        <button type="submit" class="secondary">Zoeken</button>
        <a href="/dashboard/users">Reset</a>
    </form>
</div>

<div class="card">
    <h3>Actieve users</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($activeUsers)) : ?>
            <tr><td colspan="6" class="muted">Geen users gevonden.</td></tr>
        <?php else : ?>
            <?php foreach ($activeUsers as $u) : ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($u['role'] ?? 'user'); ?></td>
                    <td><span class="badge active"><?php echo htmlspecialchars($u['status'] ?? 'active'); ?></span></td>
                    <td>
                        <?php if ($canManageUsers) : ?>
                        <form method="POST" action="/dashboard/users" class="row" style="margin-bottom:8px;">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="user_update">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($u['username'] ?? ''); ?>" placeholder="username" required>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" placeholder="email" required>
                            <input type="text" name="display_name" value="<?php echo htmlspecialchars($u['display_name'] ?? ''); ?>" placeholder="display name">
                            <select name="role">
                                <?php foreach (['user','editor','admin'] as $roleOption) : ?>
                                    <option value="<?php echo $roleOption; ?>" <?php echo (($u['role'] ?? 'user') === $roleOption) ? 'selected' : ''; ?>><?php echo $roleOption; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <?php foreach (['active','banned'] as $statusOption) : ?>
                                    <option value="<?php echo $statusOption; ?>" <?php echo (($u['status'] ?? 'active') === $statusOption) ? 'selected' : ''; ?>><?php echo $statusOption; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="secondary">Opslaan</button>
                        </form>

                        <form method="POST" action="/dashboard/users" class="row">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <input type="text" name="reason" placeholder="ban reden">
                            <input type="number" min="0" name="minutes" placeholder="minuten (0=onbepaald)">
                            <button type="submit" name="action" value="user_ban" class="warn">Ban</button>
                            <button type="submit" name="action" value="user_trash" class="secondary">Trash</button>
                        </form>
                        <?php else : ?>
                            <span class="small">Geen beheerrechten</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Banned users</h3>
    <table>
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th><th>Actie</th></tr></thead>
        <tbody>
        <?php if (empty($bannedUsers)) : ?>
            <tr><td colspan="5" class="muted">Geen blocked users.</td></tr>
        <?php else : ?>
            <?php foreach ($bannedUsers as $u) : ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                    <td><span class="badge banned"><?php echo htmlspecialchars($u['status'] ?? 'banned'); ?></span></td>
                    <td>
                        <?php if ($canManageUsers) : ?>
                        <form method="POST" action="/dashboard/users" class="row">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="user_unban">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button type="submit" class="secondary">Unban</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Trash</h3>
    <table>
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Deleted at</th><th>Actie</th></tr></thead>
        <tbody>
        <?php if (empty($trashedUsers)) : ?>
            <tr><td colspan="5" class="muted">Trash is leeg.</td></tr>
        <?php else : ?>
            <?php foreach ($trashedUsers as $u) : ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($u['deletion_requested_at'] ?? ''); ?></td>
                    <td>
                        <?php if ($canManageUsers) : ?>
                        <form method="POST" action="/dashboard/users" class="row">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="user_restore">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button type="submit" class="secondary">Restore</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
