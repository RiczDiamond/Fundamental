<?php

    // Account management view.
?>

<div class="card" id="account-card" data-dashboard-page="account">
    <div class="wrap">
        <h1 class="section-title">Mijn account</h1>

        <div id="account-alert" class="alert" style="display:none;"></div>

        <form id="account-form" class="form-table" action="#" method="post">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Actieve sessies</th>
                        <td>
                            <div id="account-sessions" style="margin-bottom:16px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_display_name">Naam</label></th>
                        <td><input id="account_display_name" type="text" placeholder="Naam"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_email">E-mail</label></th>
                        <td><input id="account_email" type="email" placeholder="E-mailadres"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_login">Gebruikersnaam</label></th>
                        <td><input id="account_login" type="text" placeholder="Gebruikersnaam"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_url">Website</label></th>
                        <td><input id="account_url" type="url" placeholder="https://voorbeeld.nl"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_current_password">Huidig wachtwoord</label></th>
                        <td><input id="account_current_password" type="password" placeholder="Huidig wachtwoord"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_new_password">Nieuw wachtwoord</label></th>
                        <td>
                            <input id="account_new_password" type="password" placeholder="Nieuw wachtwoord">
                            <div id="account-password-strength" style="margin-top:6px; font-size:12px; color: rgba(0,0,0,0.6);"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="account_new_password_confirm">Bevestig nieuw wachtwoord</label></th>
                        <td><input id="account_new_password_confirm" type="password" placeholder="Bevestig nieuw wachtwoord"></td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="button" class="btn-primary" id="account-save">Opslaan</button>
                <button type="button" class="btn-secondary" id="account-logout-other" style="margin-left:10px;">Uitloggen op andere apparaten</button>
            </p>
        </form>
    </div>
</div>
