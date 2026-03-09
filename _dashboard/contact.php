<?php

if (!is_user_logged_in()) {
    mol_safe_redirect('/login');
}

$q = sanitize_text_field($_GET['q'] ?? '');
$selectedId = absint($_GET['id'] ?? 0);
$username = (string) ($_SESSION['user_name'] ?? 'Gebruiker');
$notice = '';
$error = '';

$intent = sanitize_text_field($_POST['intent'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $intent === 'sync_imap') {
    if (!mol_require_valid_nonce('contact_sync')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $selectedId = absint($_POST['submission_id'] ?? 0);
    $q = sanitize_text_field($_POST['q'] ?? '');

    if ($error === '') {
        $syncError = null;
        $syncStats = sync_contact_inbox_imap($link, $syncError);

        if ($syncError !== null && $syncError !== '') {
            $_SESSION['contact_sync_error'] = $syncError;
        } else {
            $_SESSION['contact_sync_notice'] = sprintf(
                'IMAP sync klaar: %d nieuw, %d overgeslagen, %d verwerkt.',
                (int) ($syncStats['imported'] ?? 0),
                (int) ($syncStats['skipped'] ?? 0),
                (int) ($syncStats['processed'] ?? 0)
            );
        }
    }

    $redirect = '/dashboard/contact';
    $params = [];
    if ($selectedId > 0) {
        $params['id'] = (string) $selectedId;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($params !== []) {
        $redirect .= '?' . http_build_query($params);
    }

    if ($error === '') {
        mol_safe_redirect($redirect);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $intent === 'send_reply') {
    if (!mol_require_valid_nonce('contact_reply')) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $selectedId = absint($_POST['submission_id'] ?? 0);
    $q = sanitize_text_field($_POST['q'] ?? '');
    $subject = sanitize_text_field($_POST['reply_subject'] ?? '');
    $body = sanitize_textarea_field($_POST['reply_body'] ?? '');

    $submission = $selectedId > 0 ? get_contact_submission_by_id($link, $selectedId) : null;

    if ($error !== '') {
        // Keep existing validation flow below untouched when nonce fails.
    } elseif (!$submission) {
        $error = 'Bericht niet gevonden.';
    } elseif (!is_email((string) ($submission['email'] ?? ''))) {
        $error = 'Ontvanger e-mail is ongeldig.';
    } elseif ($subject === '' || $body === '') {
        $error = 'Onderwerp en bericht zijn verplicht.';
    } else {
        $toEmail = (string) $submission['email'];
        $fromEmail = defined('MAIL') && isset(MAIL['FROM']) ? (string) MAIL['FROM'] : 'noreply@example.com';
        $fromName = defined('MAIL') && isset(MAIL['NAME']) ? (string) MAIL['NAME'] : 'Fundamental CMS';

        $safeFromName = str_replace(["\r", "\n"], ' ', $fromName);
        $safeFromEmail = str_replace(["\r", "\n"], ' ', $fromEmail);
        $safeToEmail = str_replace(["\r", "\n"], ' ', $toEmail);

        $mailBody = $body . "\n\n--\n" . $safeFromName;
        $sendError = null;
        $sent = send_mail_phpmailer($safeToEmail, (string) ($submission['name'] ?? ''), $subject, $mailBody, $safeFromEmail, $sendError);

        if (!$sent && $sendError !== null) {
            $_SESSION['contact_reply_error'] = $sendError;
        }

        create_contact_reply($link, [
            'submission_id' => $selectedId,
            'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'to_email' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'mail_sent' => $sent,
        ]);

        $redirect = '/dashboard/contact?id=' . $selectedId;
        if ($q !== '') {
            $redirect .= '&q=' . urlencode($q);
        }
        $redirect .= $sent ? '&reply=sent' : '&reply=failed';
        mol_safe_redirect($redirect);
    }
}

    $replyStatus = sanitize_text_field($_GET['reply'] ?? '');
if ($replyStatus === 'sent') {
    $notice = 'Reply is verzonden en opgeslagen.';
}
if ($replyStatus === 'failed') {
    $error = 'Reply kon niet worden verzonden. Bericht is wel gelogd in dashboard.';

    if (isset($_SESSION['contact_reply_error']) && is_string($_SESSION['contact_reply_error']) && $_SESSION['contact_reply_error'] !== '') {
        error_log('Contact reply send failed: ' . $_SESSION['contact_reply_error']);
    }

    unset($_SESSION['contact_reply_error']);
}

if (isset($_SESSION['contact_sync_notice']) && is_string($_SESSION['contact_sync_notice']) && $_SESSION['contact_sync_notice'] !== '') {
    $notice = $_SESSION['contact_sync_notice'];
}
if (isset($_SESSION['contact_sync_error']) && is_string($_SESSION['contact_sync_error']) && $_SESSION['contact_sync_error'] !== '') {
    $error = 'IMAP sync kon niet worden uitgevoerd. Controleer de mailserver-instellingen of neem contact op met de beheerder.';
    error_log('Contact IMAP sync failed: ' . $_SESSION['contact_sync_error']);
}
unset($_SESSION['contact_sync_notice'], $_SESSION['contact_sync_error']);

$imapEnabled = defined('IMAP') && !empty(IMAP['ENABLED']);
$imapExtLoaded = function_exists('imap_open');

$submissions = get_contact_submissions($link, 250, $q);
$selected = null;
$replies = [];
$incoming = [];

if ($selectedId > 0) {
    $selected = get_contact_submission_by_id($link, $selectedId);
    if ($selected) {
        mark_contact_submission_read($link, $selectedId);
        $selected['is_read'] = 1;
        $replies = get_contact_replies($link, $selectedId);
        $incoming = get_contact_incoming_messages($link, $selectedId, 50);
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact berichten</title>
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --line:#e2e8f0; --text:#334155; --accent:#0f766e; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:"Segoe UI", Tahoma, sans-serif; }
        .admin-layout { display:grid; grid-template-columns: 250px 1fr; min-height:100vh; }
        .sidebar { background:#0f172a; color:#cbd5e1; padding:20px 14px; }
        .sidebar h2 { margin:4px 10px 14px; font-size:18px; color:#fff; }
        .sidebar a { display:block; color:#cbd5e1; text-decoration:none; padding:10px 12px; border-radius:8px; margin-bottom:4px; }
        .sidebar a:hover, .sidebar a.active { background:#1e293b; color:#fff; }
        .main { min-width:0; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .topbar strong { font-size:16px; }
        .content { padding:16px; }
        .wrap { max-width:1200px; margin:22px auto; padding:0 14px; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:12px; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        .head h1 { margin:0; font-size:24px; }
        .btn { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:9px 12px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        input, textarea { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; font:inherit; }
        textarea { resize: vertical; min-height: 120px; }
        table { width:100%; border-collapse: collapse; }
        th,td { border-top:1px solid #eef2f7; padding:10px; text-align:left; vertical-align: top; font-size:14px; }
        th { color:#475569; font-size:12px; text-transform: uppercase; letter-spacing: .06em; }
        .badge { display:inline-block; border-radius:999px; padding:2px 8px; font-size:12px; }
        .badge-new { background:#fef3c7; color:#92400e; }
        .badge-read { background:#dcfce7; color:#166534; }
        .grid { display:grid; grid-template-columns: 1fr 360px; gap:12px; }
        .message-box { white-space:pre-wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; max-height:340px; overflow:auto; }
        .meta-list { margin:0; padding:0; list-style:none; }
        .meta-list li { padding:6px 0; border-top:1px solid #eef2f7; }
        .meta-list li:first-child { border-top:0; }
        .notice, .error { border-radius:8px; padding:10px 12px; margin-bottom:10px; font-size:14px; }
        .notice { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .reply-item { border-top:1px solid #eef2f7; padding:10px 0; }
        .reply-item:first-child { border-top:0; }
        .reply-meta { font-size:12px; color:#64748b; margin-bottom:6px; }
        .reply-body { white-space:pre-wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px; }
        .hint { margin-top:8px; color:#64748b; font-size:12px; }
        @media (max-width: 980px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom:8px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <h2>Fundamental CMS</h2>
        <a href="/dashboard">Dashboard</a>
        <a href="/dashboard/pages">Pagina's</a>
        <a href="/dashboard/media">Media Library</a>
        <a href="/dashboard/menus">Menu Beheer</a>
        <a class="active" href="/dashboard/contact">Contact Berichten</a>
        <a href="/dashboard/logout">Uitloggen</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <strong>Contact Berichten</strong>
            <span>Ingelogd als <?php echo esc_html($username); ?></span>
        </header>

        <div class="content">
            <div class="wrap">
                <div class="panel">
                    <div class="head">
                        <h1>Inbox</h1>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <form method="post" action="/dashboard/contact" style="margin:0;">
                                <input type="hidden" name="intent" value="sync_imap">
                                <?php mol_nonce_field('contact_sync'); ?>
                                <input type="hidden" name="submission_id" value="<?php echo $selectedId; ?>">
                                <input type="hidden" name="q" value="<?php echo esc_attr($q); ?>">
                                <button class="btn" type="submit">IMAP sync nu</button>
                            </form>
                            <a class="btn btn-ghost" href="/dashboard">Terug naar dashboard</a>
                        </div>
                    </div>

                    <?php if ($notice !== ''): ?><div class="notice"><?php echo esc_html($notice); ?></div><?php endif; ?>
                    <?php if ($error !== ''): ?><div class="error"><?php echo esc_html($error); ?></div><?php endif; ?>

                    <form method="get" action="/dashboard/contact" style="margin-top:12px; display:grid; grid-template-columns:1fr auto; gap:10px;">
                        <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Zoek op naam, e-mail, onderwerp of bericht">
                        <button class="btn" type="submit">Zoeken</button>
                    </form>
                </div>

                <div class="grid">
                    <div class="panel">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Naam</th>
                                    <th>E-mail</th>
                                    <th>Onderwerp</th>
                                    <th>Status</th>
                                    <th>Datum</th>
                                    <th>Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($submissions)): ?>
                                <tr><td colspan="7">Nog geen berichten.</td></tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $row): ?>
                                    <?php
                                        $id = (int) ($row['id'] ?? 0);
                                        $isRead = (int) ($row['is_read'] ?? 0) === 1;
                                    ?>
                                    <tr>
                                        <td><?php echo $id; ?></td>
                                        <td><?php echo esc_html((string) ($row['name'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['email'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['subject'] ?: '(geen onderwerp)')); ?></td>
                                        <td>
                                            <span class="badge <?php echo $isRead ? 'badge-read' : 'badge-new'; ?>">
                                                <?php echo $isRead ? 'Gelezen' : 'Nieuw'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
                                        <td><a class="btn btn-ghost" href="/dashboard/contact?id=<?php echo $id; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">Bekijk</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <aside class="panel">
                        <h2 style="margin-top:0;">Bericht detail</h2>
                        <?php if (!$selected): ?>
                            <p>Selecteer een bericht om de inhoud te bekijken.</p>
                        <?php else: ?>
                            <ul class="meta-list">
                                <li><strong>ID:</strong> <?php echo (int) $selected['id']; ?></li>
                                <li><strong>Naam:</strong> <?php echo esc_html((string) ($selected['name'] ?? '')); ?></li>
                                <li><strong>E-mail:</strong> <?php echo esc_html((string) ($selected['email'] ?? '')); ?></li>
                                <li><strong>Onderwerp:</strong> <?php echo esc_html((string) ($selected['subject'] ?: '(geen onderwerp)')); ?></li>
                                <li><strong>Datum:</strong> <?php echo esc_html((string) ($selected['created_at'] ?? '')); ?></li>
                                <li><strong>URL:</strong> <?php echo esc_html((string) ($selected['page_url'] ?? '')); ?></li>
                                <li><strong>IP:</strong> <?php echo esc_html((string) ($selected['ip_address'] ?? '')); ?></li>
                            </ul>

                            <h3>Bericht</h3>
                            <div class="message-box"><?php echo esc_html((string) ($selected['message'] ?? '')); ?></div>

                            <h3 style="margin-top:14px;">Reply sturen</h3>
                            <form method="post" action="/dashboard/contact" style="display:grid; gap:8px;">
                                <input type="hidden" name="intent" value="send_reply">
                                <?php mol_nonce_field('contact_reply'); ?>
                                <input type="hidden" name="submission_id" value="<?php echo (int) $selected['id']; ?>">
                                <input type="hidden" name="q" value="<?php echo esc_attr($q); ?>">

                                <label for="reply-subject">Onderwerp</label>
                                <input id="reply-subject" type="text" name="reply_subject" value="Re: <?php echo esc_attr((string) ($selected['subject'] ?: 'Je bericht')); ?>" required>

                                <label for="reply-body">Bericht</label>
                                <textarea id="reply-body" name="reply_body" required>Hi <?php echo esc_textarea((string) ($selected['name'] ?: '')); ?>,

Dank voor je bericht. </textarea>

                                <button class="btn" type="submit">Reply verzenden</button>
                            </form>
                            <p class="hint">Inkomende antwoorden komen in jullie mailbox. Gebruik "IMAP sync nu" om ze in dit dashboard op te halen.</p>

                            <h3 style="margin-top:16px;">Reply historie</h3>
                            <?php if (empty($replies)): ?>
                                <p>Nog geen replies verzonden voor dit bericht.</p>
                            <?php else: ?>
                                <?php foreach ($replies as $reply): ?>
                                    <div class="reply-item">
                                        <div class="reply-meta">
                                            <?php echo esc_html((string) ($reply['created_at'] ?? '')); ?> |
                                            <?php echo ((int) ($reply['mail_sent'] ?? 0) === 1) ? 'Verzonden' : 'Mislukt'; ?>
                                        </div>
                                        <strong><?php echo esc_html((string) ($reply['subject'] ?: '(geen onderwerp)')); ?></strong>
                                        <div class="reply-body"><?php echo esc_html((string) ($reply['body'] ?? '')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <h3 style="margin-top:16px;">Inkomende e-mails (IMAP)</h3>
                            <?php if (empty($incoming)): ?>
                                <?php if (!$imapEnabled): ?>
                                    <p>IMAP staat uit in configuratie. Zet <code>IMAP['ENABLED']</code> op <code>true</code>.</p>
                                <?php elseif (!$imapExtLoaded): ?>
                                    <p>IMAP-extensie ontbreekt in PHP. Activeer <code>php_imap</code> en herstart Laragon.</p>
                                <?php else: ?>
                                    <p>Nog geen inkomende e-mails gekoppeld aan dit contactbericht. Klik op "IMAP sync nu".</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php foreach ($incoming as $mail): ?>
                                    <div class="reply-item">
                                        <div class="reply-meta">
                                            <?php echo esc_html((string) (($mail['received_at'] ?? '') ?: ($mail['created_at'] ?? ''))); ?> |
                                            Van: <?php echo esc_html((string) (($mail['from_name'] ?? '') !== '' ? ($mail['from_name'] . ' <' . ($mail['from_email'] ?? '') . '>') : ($mail['from_email'] ?? ''))); ?>
                                        </div>
                                        <strong><?php echo esc_html((string) (($mail['subject'] ?? '') !== '' ? $mail['subject'] : '(geen onderwerp)')); ?></strong>
                                        <div class="reply-body"><?php echo esc_html((string) ($mail['body'] ?? '')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
