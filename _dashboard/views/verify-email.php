<?php

// Email verification page (clicked from verification email).
// Loads via /dashboard/verify-email?selector=...&token=...

$selector = $_GET['selector'] ?? '';
$token = $_GET['token'] ?? '';
?>

<div class="card" id="verify-email-card" data-dashboard-page="verify-email">
    <div class="wrap">
        <h1 class="section-title">E-mail verificatie</h1>
        <div id="verify-email-alert" class="alert" style="display:none;"></div>
        <p id="verify-email-message">Even geduld, we controleren de verificatiegegevens...</p>
    </div>
</div>

<script>
    (function () {
        const $alert = $('#verify-email-alert');
        const $message = $('#verify-email-message');

        const showAlert = (msg, type = 'error') => {
            $alert.text(msg);
            $alert.attr('class', 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error'));
            $alert.show();
        };

        const selector = <?php echo json_encode($selector); ?>;
        const token = <?php echo json_encode($token); ?>;

        if (!selector || !token) {
            showAlert('Ongeldige of ontbrekende verificatiegegevens. Controleer de link en probeer opnieuw.');
            $message.hide();
            return;
        }

        $.ajax({
            url: '/resources/ajax/verify-email.php',
            method: 'GET',
            dataType: 'json',
            data: { selector: selector, token: token },
        })
            .done((res) => {
                $message.text(res.success || 'Uw e-mailadres is geverifieerd.');
                showAlert(res.success || 'Uw e-mailadres is geverifieerd.', 'success');
            })
            .fail((xhr) => {
                const msg = xhr.responseJSON?.error || 'Verificatie mislukt.';
                showAlert(msg);
                $message.hide();
            });
    })();
</script>
