<?php
    if (is_user_logged_in()) {
        mol_safe_redirect('/dashboard/pages');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!mol_require_valid_nonce('dashboard_login')) {
            $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
        }

        if (empty($error)) {
            $user = mol_signon([
                'user_login' => $_POST['username'] ?? '',
                'user_password' => $_POST['password'] ?? '',
                'remember' => !empty($_POST['remember']),
            ]);

            if ($user !== false) {
                mol_safe_redirect('/dashboard/pages');
            }

            $error = 'Ongeldige gebruikersnaam of wachtwoord';
        }
    }
?>