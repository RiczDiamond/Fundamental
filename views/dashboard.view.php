<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
?>

<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard</title>
    <style>
        body{font-family:Segoe UI,Arial,Helvetica,sans-serif;background:#f7f9fb;color:#222;margin:0;padding:1.5rem}
        .container{max-width:760px;margin:0 auto;background:#fff;padding:1.25rem;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
        .meta{color:#555;font-size:.95rem}
        .profile{display:flex;gap:1rem;align-items:center}
        .avatar{width:64px;height:64px;background:#e9eef6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;color:#4066b0}
        .actions{margin-top:.75rem}
        a.button{display:inline-block;padding:.45rem .8rem;background:#2b6cb0;color:#fff;border-radius:6px;text-decoration:none}
        .notice{color:#8b8b8b}
    </style>
</head>
<body>
<div class="container">
    <h1>Dashboard</h1>

    <?php if (!$user_id): ?>
        <p class="notice">Niet ingelogd. <a href="/" class="button">Inloggen</a></p>
    <?php else: ?>

        <?php
        // Zorg dat $link beschikbaar is en een PDO-instance
        if (isset($link) && $link instanceof PDO) {
            $stmt = $link->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $user = null;
        }
        ?>

        <?php if ($user): ?>
            <section class="profile">
                <div class="avatar" aria-hidden="true"><?php echo strtoupper(substr(htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'), 0, 1)); ?></div>
                <div>
                    <div><strong>Ingelogd als:</strong> <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="meta"><strong>Email:</strong> <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="actions">
                        <a href="/logout" class="button">Uitloggen</a>
                        <a href="/" style="margin-left:.5rem">Terug naar site</a>
                    </div>
                </div>
            </section>
            
            <hr>
            <h2>Mijn account bewerken</h2>
            <div id="myAccount">
                <form id="accountForm" onsubmit="return false;" style="max-width:640px">
                    <input type="hidden" id="edit_id" name="id" value="">
                    <div style="margin-bottom:.5rem"><label>Gebruikersnaam<br><input name="username" id="username" style="width:100%"></label></div>
                    <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
                        <label style="flex:1">Voornaam<br><input name="first_name" id="first_name" style="width:100%"></label>
                        <label style="flex:1">Achternaam<br><input name="last_name" id="last_name" style="width:100%"></label>
                    </div>
                    <div style="margin-bottom:.5rem"><label>Weergavenaam<br><input name="display_name" id="display_name" style="width:100%"></label></div>
                    <div style="margin-bottom:.5rem"><label>Email<br><input name="email" id="email" type="email" style="width:100%"></label></div>
                    <div style="margin-bottom:.5rem"><label>Geboortedatum<br><input name="birth_date" id="birth_date" type="date" style="width:100%"></label></div>
                    <div style="margin-bottom:.5rem"><label>Nieuw wachtwoord (laat leeg om te behouden)<br><input name="password" id="password" type="password" style="width:100%"></label></div>
                    <div style="margin-bottom:.5rem"><label>Bio<br><textarea name="bio" id="bio" style="width:100%" rows="3"></textarea></label></div>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <button id="saveAccount" class="button">Opslaan</button>
                        <button id="deleteAccount" class="button" style="background:#c53030">Account Verwijderen</button>
                        <span id="accountNotice" class="meta" style="margin-left:.6rem"></span>
                    </div>
                </form>
            </div>

            <script>
                (function(){
                    const csrf = '<?php echo $csrfToken; ?>';
                    const notice = document.getElementById('accountNotice');
                    async function load(){
                        // load current user into form
                        const res = await fetch('/modules/dashboard.php?action=me');
                        const data = await res.json();
                        if (!data.ok) { notice.textContent = 'Kon account niet laden'; return; }
                        const u = data.user || {};
                        fillForm(u);
                        document.getElementById('edit_id').value = '';
                    }

                    function fillForm(u){
                        document.getElementById('username').value = u.username || '';
                        document.getElementById('first_name').value = u.first_name || '';
                        document.getElementById('last_name').value = u.last_name || '';
                        document.getElementById('display_name').value = u.display_name || '';
                        document.getElementById('email').value = u.email || '';
                        document.getElementById('birth_date').value = u.birth_date || '';
                        document.getElementById('bio').value = u.bio || '';
                        document.getElementById('password').value = '';
                    }

                    document.getElementById('saveAccount').addEventListener('click', async function(e){
                        e.preventDefault(); notice.textContent = 'Opslaan...';
                        const form = new FormData();
                        form.append('csrf', csrf);
                        const editId = document.getElementById('edit_id').value;
                        if (editId) form.append('id', editId);
                        form.append('username', document.getElementById('username').value);
                        form.append('first_name', document.getElementById('first_name').value);
                        form.append('last_name', document.getElementById('last_name').value);
                        form.append('display_name', document.getElementById('display_name').value);
                        form.append('email', document.getElementById('email').value);
                        form.append('birth_date', document.getElementById('birth_date').value);
                        const pw = document.getElementById('password').value;
                        if (pw) form.append('password', pw);
                        form.append('bio', document.getElementById('bio').value);
                        const res = await fetch('/modules/dashboard.php?action=update', { method: 'POST', body: form });
                        const data = await res.json();
                        notice.textContent = data.ok ? 'Opgeslagen' : ('Fout: '+(data.error||''));
                        setTimeout(()=>notice.textContent = '', 2500);
                        if (typeof listUsers === 'function') listUsers();
                    });

                    document.getElementById('deleteAccount').addEventListener('click', async function(e){
                        e.preventDefault();
                        if (!confirm('Weet je zeker dat je dit account wilt verwijderen? Dit is soft-delete.')) return;
                        notice.textContent = 'Verwijderen...';
                        const form = new FormData();
                        form.append('csrf', csrf);
                        const editId = document.getElementById('edit_id').value;
                        if (editId) form.append('id', editId);
                        const res = await fetch('/modules/dashboard.php?action=delete', { method: 'POST', body: form });
                        const data = await res.json();
                        if (data.ok) {
                            notice.textContent = 'Account verwijderd.';
                            const current = '<?php echo $user_id; ?>';
                            if (!editId || editId === current) {
                                setTimeout(()=>{ window.location = '/'; }, 1200);
                            } else {
                                if (typeof listUsers === 'function') listUsers();
                            }
                        } else {
                            notice.textContent = 'Fout: ' + (data.error||'');
                            setTimeout(()=>notice.textContent = '', 2500);
                        }
                    });

                    load();
                })();
            </script>
            
            <?php if (isset($auth) && $auth->perm_check(null, 'admin')): ?>
                <hr>
                <h2>Admin: Gebruikersbeheer</h2>
                <div id="admin">
                    <div style="margin-bottom:.5rem">
                        <button id="refresh" class="button">Ververs lijst</button>
                        <span id="adminNotice" class="meta" style="margin-left:.6rem"></span>
                    </div>

                    <div id="usersWrap">Laden...</div>

                    <div id="userDetail" style="margin-top:1rem;display:none;padding:.75rem;border:1px solid #eee;border-radius:6px;background:#fafafa"></div>
                </div>
                <script>
                    (function(){
                        const usersWrap = document.getElementById('usersWrap');
                        const userDetail = document.getElementById('userDetail');
                        const adminNotice = document.getElementById('adminNotice');

                        async function listUsers(){
                            usersWrap.textContent = 'Laden...';
                            const res = await fetch('/modules/dashboard.php?action=list');
                            const data = await res.json();
                            if (!data.ok) { usersWrap.textContent = 'Fout bij ophalen'; return; }
                            const users = data.users || [];
                            if (!users.length) { usersWrap.textContent = 'Geen gebruikers gevonden'; return; }
                            const table = document.createElement('table');
                            table.style.width = '100%';
                            table.style.borderCollapse = 'collapse';
                            let html = '<tr style="text-align:left;color:#666"><th>#</th><th>Gebruiker</th><th>Email</th><th>Gestopt</th><th>Acties</th></tr>';
                            users.forEach(u => {
                                html += `<tr style="border-top:1px solid #eee"><td>${u.id}</td><td>${escapeHtml(u.username)}</td><td>${escapeHtml(u.email||'')}</td><td>${u.banned? 'Ja':'Nee'}</td><td><a href="#" data-id="${u.id}" class="view">Bekijk</a> | <a href="#" data-id="${u.id}" class="toggleBan">${u.banned? 'Deban':'Ban'}</a></td></tr>`;
                            });
                            usersWrap.innerHTML = html;
                            Array.from(document.querySelectorAll('#usersWrap a.view')).forEach(a=>a.addEventListener('click', async function(e){
                                e.preventDefault(); const id = this.dataset.id; await viewUser(id);
                            }));
                            Array.from(document.querySelectorAll('#usersWrap a.toggleBan')).forEach(a=>a.addEventListener('click', async function(e){
                                e.preventDefault(); const id = this.dataset.id; await toggleBan(id, this);
                            }));
                        }

                        async function viewUser(id){
                            userDetail.style.display = 'block';
                            userDetail.textContent = 'Laden...';
                            const res = await fetch('/modules/dashboard.php?action=view&id='+encodeURIComponent(id));
                            const data = await res.json();
                            if (!data.ok) { userDetail.textContent = 'Fout: '+(data.error||''); return; }
                            const u = data.user || {};
                            const groups = (data.groups || []).map(g=>g.group_name).join(', ');
                            const perms = (data.permissions || []).join(', ');
                            userDetail.innerHTML = `<strong>${escapeHtml(u.username||'')}</strong> &nbsp; <span class="meta">${escapeHtml(u.email||'')}</span><div style="margin-top:.5rem"><strong>Groepen:</strong> ${escapeHtml(groups)}</div><div style="margin-top:.25rem"><strong>Permissies:</strong> ${escapeHtml(perms)}</div><div style="margin-top:.5rem">${escapeHtml(u.comment||'')}</div>`;
                                // also populate edit form so admin can edit this user
                                try {
                                    if (document.getElementById('edit_id')) {
                                        document.getElementById('edit_id').value = u.id || '';
                                        // fill form fields if present
                                        if (u.username) document.getElementById('username').value = u.username;
                                        if (u.first_name) document.getElementById('first_name').value = u.first_name;
                                        if (u.last_name) document.getElementById('last_name').value = u.last_name;
                                        if (u.display_name) document.getElementById('display_name').value = u.display_name;
                                        if (u.email) document.getElementById('email').value = u.email;
                                        if (u.birth_date) document.getElementById('birth_date').value = u.birth_date;
                                        if (u.bio) document.getElementById('bio').value = u.bio;
                                        document.getElementById('password').value = '';
                                    }
                                } catch (e) {
                                    // ignore
                                }
                        }

                        async function toggleBan(id, anchor){
                            const confirmMsg = 'Weet je het zeker?';
                            if (!confirm(confirmMsg)) return;
                            adminNotice.textContent = 'Verwerken...';
                            const form = new FormData();
                            form.append('id', id);
                            form.append('csrf', '<?php echo $csrfToken; ?>');
                            // decide action based on anchor text
                            const action = anchor.textContent.trim().toLowerCase() === 'ban' ? 'ban' : 'unban';
                            const res = await fetch('/modules/dashboard.php?action='+action, { method: 'POST', body: form });
                            const data = await res.json();
                            adminNotice.textContent = data.ok ? 'Klaar' : ('Fout: '+(data.error||''));
                            listUsers();
                            setTimeout(()=>adminNotice.textContent = '', 2500);
                        }

                        function escapeHtml(s){ return String(s||'').replace(/[&"'<>]/g, c=>({'&':'&amp;','"':'&quot;','"':'&quot;','\'':'&#39;','<':'&lt;','>':'&gt;'}[c])); }

                        document.getElementById('refresh').addEventListener('click', listUsers);
                        listUsers();
                    })();
                </script>
            <?php endif; ?>
        <?php else: ?>
            <p>Gebruiker niet gevonden. <a href="/logout" class="button">Uitloggen</a></p>
        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>