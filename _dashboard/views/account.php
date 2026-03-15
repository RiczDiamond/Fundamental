<?php

    // Account management view.
?>

<div class="card" id="account-card" data-dashboard-page="account">
    <h2 class="section-title">Mijn account</h2>

    <div id="account-alert" style="display:none;" class="alert"></div>

    <div class="form-group">
        <label for="account_display_name">Naam</label>
        <input id="account_display_name" type="text" placeholder="Naam">
    </div>

    <div class="form-group">
        <label for="account_email">E-mail</label>
        <input id="account_email" type="email" placeholder="E-mailadres">
    </div>

    <div class="form-group">
        <label for="account_login">Gebruikersnaam</label>
        <input id="account_login" type="text" placeholder="Gebruikersnaam">
    </div>

    <div class="form-group">
        <label for="account_url">Website</label>
        <input id="account_url" type="url" placeholder="https://voorbeeld.nl">
    </div>

    <div class="form-group">
        <label for="account_current_password">Huidig wachtwoord</label>
        <input id="account_current_password" type="password" placeholder="Huidig wachtwoord">
    </div>

    <div class="form-group">
        <label for="account_new_password">Nieuw wachtwoord</label>
        <input id="account_new_password" type="password" placeholder="Nieuw wachtwoord">
    </div>

    <div class="form-group">
        <label for="account_new_password_confirm">Bevestig nieuw wachtwoord</label>
        <input id="account_new_password_confirm" type="password" placeholder="Bevestig nieuw wachtwoord">
    </div>

    <button class="btn-primary" id="account-save">Opslaan</button>
