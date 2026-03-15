<?php

    // Dashboard home view (summary / quick links).
?>

<div class="card">
    <h2 class="section-title">Welkom in het Dashboard</h2>
    <p>Gebruik het menu links om gebruikers, posts of andere onderdelen te beheren.</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 20px;">
        <a href="/dashboard/account" class="card" style="text-decoration:none; color:inherit;">
            <h3 style="margin:0 0 8px;">Mijn account</h3>
            <p style="margin:0; color: var(--muted);">Bekijk of wijzig je profielgegevens.</p>
        </a>
        <a href="/dashboard/posts" class="card" style="text-decoration:none; color:inherit;">
            <h3 style="margin:0 0 8px;">Posts</h3>
            <p style="margin:0; color: var(--muted);">Beheer blogs / pagina's via de API.</p>
        </a>
    </div>
</div>
