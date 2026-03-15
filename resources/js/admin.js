$(function () {
    // ---------- Helpers ----------
    const getCsrfToken = () => {
        const meta = $('meta[name="csrf-token"]').attr('content');
        if (meta) {
            return $.Deferred().resolve(meta).promise();
        }

        return $.ajax({
            url: '/resources/ajax/get-nonce.php',
            dataType: 'json',
            xhrFields: { withCredentials: true },
        }).then((res) => res.nonce || '');
    };

    const ajaxWithCsrf = ({ url, method = 'GET', data = null }) => {
        const settings = {
            url,
            method,
            dataType: 'json',
            xhrFields: { withCredentials: true },
        };

        if (data !== null) {
            settings.contentType = 'application/json';
            settings.data = JSON.stringify(data);
        }

        return getCsrfToken().then((token) => {
            settings.headers = {
                'X-CSRF-Token': token,
                'X-CSRF-Action': 'global_csrf',
            };
            return $.ajax(settings);
        });
    };

    const showAlert = ($el, message, type = 'error', duration = 5000) => {
        $el.text(message);
        $el.attr('class', 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error'));
        $el.show();
        setTimeout(() => { $el.hide(); }, duration);
    };

    // Global toggle handler for password visibility buttons (works even if button is in HTML)
    $(document).on('click', '.password-toggle', function () {
        const $toggle = $(this);
        const $input = $toggle.siblings('input[type="password"], input[type="text"]').first();
        if (!$input.length) return;

        const isPassword = $input.attr('type') === 'password';
        $input.attr('type', isPassword ? 'text' : 'password');

        // Toggle visibility icons
        $toggle.toggleClass('active', isPassword);
        $toggle.find('.dashicons-visibility').toggle(!isPassword);
        $toggle.find('.dashicons-hidden').toggle(isPassword);
    });

    const startButtonLoading = ($btn, text = 'Bezig...') => {
        $btn.prop('disabled', true);
        $btn.data('orig-text', $btn.text());
        $btn.text(text);
        $btn.addClass('loading');
    };

    const stopButtonLoading = ($btn) => {
        $btn.prop('disabled', false);
        const orig = $btn.data('orig-text');
        if (orig) {
            $btn.text(orig);
        }
        $btn.removeClass('loading');
    };

    function enablePasswordToggle($container) {
        const $pwFields = $container.find('input[type="password"]').not('.password-toggle-enabled');
        $pwFields.each(function () {
            const $input = $(this);
            $input.addClass('password-toggle-enabled');

            const $group = $input.closest('.input-group');
            if ($group.length) {
                $group.addClass('password');
            }

            const $toggle = $(
                '<button type="button" class="password-toggle" aria-label="Toon/verberg wachtwoord">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10z" fill="currentColor"/><path d="M12 9a3 3 0 0 0 0 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
                '</button>'
            );

            $input.after($toggle);

            $toggle.on('click', () => {
                const type = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
            });
        });
    }

    // ---------- Login ----------
    if ($('#loginForm').length) {
        const $form = $('#loginForm');
        const $submit = $('#submitBtn');
        const $twoFactor = $('#two-factor-form');
        const $twoFactorCode = $('#two-factor-code');
        const $twoFactorRecovery = $('#two-factor-recovery');
        const $twoFactorSubmit = $('#two-factor-submit');
        const $twoFactorCancel = $('#two-factor-cancel');
        const hasTwoFactor = $twoFactor.length > 0;

        const startLogin = () => {
            $submit.addClass('loading in-progress');
            $submit.text('Bezig met inloggen...');
        };

        const endLogin = () => {
            $submit.removeClass('loading in-progress');
            $submit.text('Inloggen');
        };

        const showTwoFactor = () => {
            $form.hide();
            $twoFactor.show();
        };

        const hideTwoFactor = () => {
            $twoFactor.hide();
            $form.show();
            $twoFactorCode.val('');
            $twoFactorRecovery.val('');
        };

        const showError = (msg) => {
            let $error = $('.error-message');
            if (!$error.length) {
                $error = $('<div class="error-message"></div>');
                $('.form-container').prepend($error);
            }
            $error.text(msg);
        };

        const clearError = () => {
            $('.error-message').remove();
        };

        $form.on('submit', function (event) {
            event.preventDefault();
            clearError();

            const data = {
                action: 'login',
                user_login: $('#username').val(),
                user_password: $('#password').val(),
                remember: $('#remember').is(':checked'),
            };

            startLogin();

            ajaxWithCsrf({
                url: '/resources/ajax/auth.php',
                method: 'POST',
                data: { ...data, _nonce_action: 'global_csrf' },
            })
                .done((res) => {
                    if (hasTwoFactor && res.two_factor) {
                        showTwoFactor();
                        return;
                    }
                    window.location.href = '/dashboard';
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Inloggen mislukt';
                    showError(msg);
                })
                .always(endLogin);
        });

        if (hasTwoFactor) {
            $twoFactorSubmit.on('click', function () {
                const code = $twoFactorCode.val().trim();
                const recovery = $twoFactorRecovery.val().trim();
                if (!code && !recovery) {
                    showError('Voer een 2FA-code of herstelcode in.');
                    return;
                }

                startLogin();

                ajaxWithCsrf({
                    url: '/resources/ajax/auth.php',
                    method: 'POST',
                    data: { action: 'verify_2fa', code, recovery_code: recovery, _nonce_action: 'global_csrf' },
                })
                    .done(() => {
                        window.location.href = '/dashboard';
                    })
                    .fail((xhr) => {
                        const msg = xhr.responseJSON?.error || 'Ongeldige 2FA-code.';
                        showError(msg);
                    })
                    .always(endLogin);
            });

            $twoFactorCancel.on('click', () => {
                hideTwoFactor();
            });
        }

        $twoFactorCancel.on('click', () => {
            hideTwoFactor();
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                endLogin();
            }
        });

        // Ensure password visibility toggle works on the login screen
        enablePasswordToggle($(document));

        $('#login-recovery-email').on('click', function () {
            const username = $('#username').val().trim();
            if (!username) {
                showError('Voer uw gebruikersnaam of e-mailadres in om herstelcodes te verzenden.');
                return;
            }

            const $btn = $(this);
            startButtonLoading($btn, 'Verzenden...');

            ajaxWithCsrf({
                url: '/resources/ajax/2fa-recovery.php',
                method: 'POST',
                data: { username, _nonce_action: 'global_csrf' },
            })
                .done(() => {
                    showError('Indien het account bestaat, ontvangt u een e-mail met herstelcodes.');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon de herstelcodes niet verzenden.';
                    showError(msg);
                })
                .always(() => {
                    stopButtonLoading($btn);
                });
        });

        return;
    }

    // ---------- Forgot password ----------
    if ($('#forgot-request-form').length || $('#forgot-reset-form').length) {
        const $alert = $('#forgot-alert');
        const $requestBlock = $('#forgot-request');
        const $resetBlock = $('#forgot-reset');
        const $successBlock = $('#forgot-success');
        const $successMessage = $('#forgot-success-message');

        const queryParams = new URLSearchParams(window.location.search);
        const selector = queryParams.get('selector') || '';
        const token = queryParams.get('token') || '';

        const showBlock = (block) => {
            $requestBlock.hide();
            $resetBlock.hide();
            $successBlock.hide();

            if (block === 'request') $requestBlock.show();
            if (block === 'reset') $resetBlock.show();
            if (block === 'success') $successBlock.show();
        };

        if (selector && token) {
            showBlock('reset');
        } else {
            showBlock('request');
        }

        const $requestSubmit = $('#forgot-request-submit');
        const $resetSubmit = $('#forgot-reset-submit');

        const makeForgotRequest = (payload) =>
            ajaxWithCsrf({
                url: '/resources/ajax/forgot-password.php',
                method: 'POST',
                data: payload,
            });

        $('#forgot-request-form').on('submit', function (event) {
            event.preventDefault();
            startButtonLoading($requestSubmit);

            makeForgotRequest({
                action: 'request',
                username: $('#forgot_username').val().trim(),
            })
                .done((res) => {
                    showBlock('success');
                    $successMessage.text(res.success || 'Check uw e-mail voor de resetlink.');

                    if (res.debug) {
                        console.debug('Forgot-password debug:', res.debug);
                        showAlert($alert, 'Debug: ' + res.debug, 'success');
                    }
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Er is iets misgegaan. Probeer het opnieuw.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($requestSubmit);
                });
        });

        $('#forgot-reset-form').on('submit', function (event) {
            event.preventDefault();
            startButtonLoading($resetSubmit);

            makeForgotRequest({
                action: 'reset',
                selector,
                token,
                new_password: $('#forgot_new_password').val().trim(),
                new_password_confirm: $('#forgot_new_password_confirm').val().trim(),
            })
                .done((res) => {
                    showBlock('success');
                    $successMessage.text(res.success || 'Uw wachtwoord is gewijzigd. U kunt nu inloggen.');

                    if (res.debug) {
                        console.debug('Forgot-password debug:', res.debug);
                        showAlert($alert, 'Debug: ' + res.debug, 'success');
                    }
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Er is iets misgegaan. Probeer het opnieuw.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($resetSubmit);
                });
        });

        return;
    }

    // ---------- Account ----------
    if ($('#account-card').length) {
        const $alert = $('#account-alert');

        const fillForm = (user) => {
            $('#account_display_name').val(user.display_name ?? '');
            $('#account_email').val(user.user_email ?? '');
            $('#account_login').val(user.user_login ?? '');
            $('#account_url').val(user.user_url ?? '');
        };

        const renderSessions = (sessions = [], currentSessionId) => {
            const $container = $('#account-sessions');
            if (!sessions.length) {
                $container.html('<div style="color: rgba(0,0,0,0.6);">Geen actieve sessies gevonden.</div>');
                return;
            }

            const items = sessions.map((s) => {
                const isCurrent = s.id === currentSessionId;
                const label = isCurrent ? 'Huidige sessie' : 'Andere sessie';
                const date = s.created_at ? new Date(s.created_at).toLocaleString() : 'Onbekend';
                const userAgent = s.user_agent || 'Onbekend apparaat';
                const ip = s.ip || 'Onbekend IP';

                return `
                    <div class="session-row" style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.08);">
                        <div style="flex:1;">
                            <div style="font-weight:600;">${label}</div>
                            <div style="font-size:12px; color: rgba(0,0,0,0.7);">${userAgent} · ${ip}</div>
                            <div style="font-size:12px; color: rgba(0,0,0,0.55);">${date}</div>
                        </div>
                        ${isCurrent ? '<span style="font-size:12px; color: rgba(0,0,0,0.5);">(actief)</span>' : '<button type="button" class="btn-secondary btn-logout-session" data-session="' + s.id + '" style="font-size:12px;">Uitloggen</button>'}
                    </div>
                `;
            });

            $container.html(items.join(''));
        };

        const renderTwoFactorStatus = (enabled, pending) => {
            const $status = $('#account-2fa-status');
            const $setup = $('#account-2fa-setup');

            if (enabled) {
                $status.html('<strong style="color: #2e7d32;">2FA is ingeschakeld</strong> <button id="account-2fa-disable" class="btn-secondary" style="margin-left:6px;">Uitschakelen</button>');
                $setup.hide();
                return;
            }

            if (pending) {
                $status.html('<strong style="color: #f57c00;">2FA is in voorbereiding. Voltooi verificatie.</strong>');
                $setup.show();
                return;
            }

            $status.html('<strong style="color: #d32f2f;">2FA is uitgeschakeld.</strong> <button id="account-2fa-enable" class="btn-primary" style="margin-left:6px;">Inschakelen</button>');
            $setup.hide();
        };

        const showTwoFactorSetup = (secret, otpauth) => {
            $('#account-2fa-secret').text(secret);

            // Use a public QR generator (avoids blocked Google Charts and ensures reliable rendering).
            const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(otpauth);
            $('#account-2fa-qr').attr('src', qrUrl);

            $('#account-2fa-setup').show();
        };

        const hideTwoFactorSetup = () => {
            $('#account-2fa-setup').hide();
            $('#account-2fa-code').val('');
            $('#account-2fa-recovery-list').hide().html('');
        };

        const loadAccount = () => {
            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'GET' })
                .done((res) => {
                    fillForm(res.user || {});
                    renderSessions(res.sessions || [], res.current_session);
                    renderTwoFactorStatus(res.two_factor_enabled, res.two_factor_pending);

                    // If 2FA is pending, ensure the QR/secret are shown.
                    if (res.two_factor_pending && res.two_factor_pending_secret && res.two_factor_pending_otpauth) {
                        showTwoFactorSetup(res.two_factor_pending_secret, res.two_factor_pending_otpauth);
                    }
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon account niet laden.';
                    showAlert($alert, msg);

                    // Debug: log response details in the console (helpful when JSON isn't returned)
                    console.error('Account load failed:', xhr.status, xhr.statusText);
                    if (xhr.responseText) {
                        console.debug('Response text:', xhr.responseText);
                    }
                });
        };

        const debounced = (fn, delay = 400) => {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        };

        const passwordStrength = (pw) => {
            let score = 0;
            if (pw.length >= 8) score += 1;
            if (/[A-Z]/.test(pw)) score += 1;
            if (/[0-9]/.test(pw)) score += 1;
            if (/[^A-Za-z0-9]/.test(pw)) score += 1;
            return score;
        };

        const updatePasswordStrength = () => {
            const pw = $('#account_new_password').val();
            const $info = $('#account-password-strength');
            if (!pw) {
                $info.text('');
                return;
            }
            const score = passwordStrength(pw);
            const labels = ['Te zwak', 'Zwak', 'Gemiddeld', 'Sterk', 'Zeer sterk'];
            const colors = ['#d32f2f', '#f57c00', '#fbc02d', '#388e3c', '#2e7d32'];
            $info.text(labels[score]).css('color', colors[score]);
        };

        const validateUnique = (field, value) => {
            if (!value) return;
            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'PUT', data: { check_unique: true, [field]: value } })
                .done(() => {
                    // OK
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error;
                    if (msg) showAlert($alert, msg);
                });
        };

        $('#account_new_password').on('input', updatePasswordStrength);
        $('#account_email').on('blur', debounced(() => validateUnique('user_email', $('#account_email').val().trim())));
        $('#account_login').on('blur', debounced(() => validateUnique('user_login', $('#account_login').val().trim())));

        const saveAccount = () => {
            const currentPassword = $('#account_current_password').val().trim();
            const newPassword = $('#account_new_password').val().trim();
            const confirmPassword = $('#account_new_password_confirm').val().trim();

            if (newPassword && newPassword !== confirmPassword) {
                showAlert($alert, 'Nieuw wachtwoord en bevestiging komen niet overeen.');
                return;
            }

            let userUrl = $('#account_url').val().trim();
            if (userUrl && !/^https?:\/\//i.test(userUrl)) {
                userUrl = 'https://' + userUrl;
                $('#account_url').val(userUrl);
            }

            const data = {
                display_name: $('#account_display_name').val(),
                user_email: $('#account_email').val(),
                user_login: $('#account_login').val(),
                user_url: userUrl,
            };

            if (currentPassword && newPassword) {
                data.current_password = currentPassword;
                data.new_password = newPassword;
            }

            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'PUT', data })
                .done((res) => {
                    const msg = res.pending_email_notice || 'Accountgegevens opgeslagen.';
                    showAlert($alert, msg, 'success');

                    // Check that the server actually saved the website/url field.
                    const sentUrl = data.user_url?.trim();
                    const savedUrl = res.updated?.user_url?.trim();
                    if (sentUrl && (!savedUrl || sentUrl !== savedUrl)) {
                        showAlert($alert, 'Website kon niet worden opgeslagen. Controleer de waarde.', 'error');
                    }

                    $('#account_current_password, #account_new_password, #account_new_password_confirm').val('');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                    showAlert($alert, msg);
                });
        };

        $('#account-save').on('click', saveAccount);
        $('#account-logout-other').on('click', () => {
            if (!confirm('Weet je zeker dat je op andere apparaten wilt uitloggen?')) {
                return;
            }

            ajaxWithCsrf({ url: '/resources/ajax/logout-all-sessions.php', method: 'POST' })
                .done((res) => {
                    showAlert($alert, res.success || 'Uitgelogd op andere apparaten.', 'success');
                    loadAccount();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon niet uitloggen op andere apparaten.';
                    showAlert($alert, msg);
                });
        });

        $(document).on('click', '.btn-logout-session', function () {
            const sessionId = $(this).data('session');

            if (!sessionId) {
                return;
            }

            ajaxWithCsrf({
                url: '/resources/ajax/logout-session.php',
                method: 'POST',
                data: { session_id: sessionId },
            })
                .done((res) => {
                    showAlert($alert, res.success || 'Sessie uitgelogd.', 'success');
                    loadAccount();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon sessie niet verwijderen.';
                    showAlert($alert, msg);
                });
        });

        $(document).on('click', '#account-2fa-enable', function () {
            startButtonLoading($(this), 'Bezig...');
            ajaxWithCsrf({ url: '/resources/ajax/2fa.php', method: 'POST', data: { action: 'generate' } })
                .done((res) => {
                    if (res.secret && res.otpauth) {
                        showTwoFactorSetup(res.secret, res.otpauth);
                        renderTwoFactorStatus(false, true);
                    } else {
                        showAlert($alert, 'Kon 2FA niet genereren.');
                    }
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon 2FA niet genereren.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($(this));
                });
        });

        $(document).on('click', '#account-2fa-disable', function () {
            if (!confirm('Weet je zeker dat je 2FA wilt uitschakelen?')) {
                return;
            }

            startButtonLoading($(this), 'Bezig...');
            ajaxWithCsrf({ url: '/resources/ajax/2fa.php', method: 'POST', data: { action: 'disable' } })
                .done(() => {
                    showAlert($alert, '2FA uitgeschakeld.', 'success');
                    hideTwoFactorSetup();
                    renderTwoFactorStatus(false, false);
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon 2FA niet uitschakelen.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($(this));
                });
        });

        $(document).on('click', '#account-2fa-verify', function () {
            const code = $('#account-2fa-code').val().trim();
            if (!code) {
                showAlert($alert, 'Voer een 2FA-code in.');
                return;
            }

            startButtonLoading($(this), 'Controleren...');
            ajaxWithCsrf({ url: '/resources/ajax/2fa.php', method: 'POST', data: { action: 'verify', code } })
                .done(() => {
                    showAlert($alert, '2FA is ingeschakeld!', 'success');
                    hideTwoFactorSetup();
                    renderTwoFactorStatus(true, false);
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Ongeldige 2FA-code.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($(this));
                });
        });

        $(document).on('click', '#account-2fa-recovery', function () {
            startButtonLoading($(this), 'Bezig...');
            ajaxWithCsrf({ url: '/resources/ajax/2fa.php', method: 'POST', data: { action: 'recovery_codes' } })
                .done((res) => {
                    const list = (res.codes || []).map((c) => `<div style="padding:4px 0;">${c}</div>`).join('');
                    $('#account-2fa-recovery-list').html(list).show();
                    showAlert($alert, 'Herstelcodes gegenereerd. Bewaar ze op een veilige plek.', 'success');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon herstelcodes niet genereren.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($(this));
                });
        });

        $(document).on('click', '#account-2fa-clear-recovery', function () {
            startButtonLoading($(this), 'Bezig...');
            ajaxWithCsrf({ url: '/resources/ajax/2fa.php', method: 'POST', data: { action: 'clear_recovery_codes' } })
                .done(() => {
                    $('#account-2fa-recovery-list').hide().html('');
                    showAlert($alert, 'Herstelcodes gewist.', 'success');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon herstelcodes niet wissen.';
                    showAlert($alert, msg);
                })
                .always(() => {
                    stopButtonLoading($(this));
                });
        });

        loadAccount();
    }

    // ---------- Audit log ----------
    if ($('#audit-card').length) {
        const $alert = $('#audit-alert');
        const $tbody = $('#audit-table tbody');
        const $pagination = $('#audit-pagination');
        const $filterAction = $('#audit-filter-action');
        const $filterActor = $('#audit-filter-actor');
        const $filterTarget = $('#audit-filter-target');
        const $filterSince = $('#audit-filter-since');
        const $filterUntil = $('#audit-filter-until');
        const $refresh = $('#audit-refresh');

        let auditPage = 1;
        const auditPerPage = 25;

        const renderAuditRows = (items) => {
            if (!items || !items.length) {
                $tbody.html('<tr><td colspan="5" style="padding:16px; text-align:center; color:#888;">Geen auditlogs gevonden.</td></tr>');
                return;
            }

            const rows = items.map((item) => {
                const actor = item.actor_display ? `${item.actor_display} (${item.actor_login})` : item.actor_login || item.actor_id || '—';
                const target = item.target_display ? `${item.target_display} (${item.target_login})` : item.target_login || item.target_id || '—';
                const meta = item.meta ? JSON.stringify(item.meta) : '';
                const date = item.created_at ? new Date(item.created_at).toLocaleString() : '';
                return `
                    <tr>
                        <td style="padding:10px; border-bottom:1px solid #eee; white-space:nowrap;">${date}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee;">${item.action}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee;">${actor}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee;">${target}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee; font-family:monospace; font-size:12px; color:#444;">${meta}</td>
                    </tr>
                `;
            });

            $tbody.html(rows.join(''));
        };

        const updatePagination = (total, page, perPage) => {
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            auditPage = Math.min(Math.max(1, page), totalPages);

            if (totalPages <= 1) {
                $pagination.html('');
                return;
            }

            const buttons = [];
            for (let p = 1; p <= totalPages; p += 1) {
                const active = p === auditPage ? 'background:#333;color:#fff;' : '';
                buttons.push(`<button data-page="${p}" style="padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer; ${active}">${p}</button>`);
            }

            $pagination.html(buttons.join(''));
        };

        const loadAudit = () => {
            const params = new URLSearchParams();
            params.set('page', String(auditPage));
            params.set('per_page', String(auditPerPage));

            const action = $filterAction.val().trim();
            if (action) params.set('action', action);

            const actor = $filterActor.val().trim();
            if (actor) params.set('actor', actor);

            const target = $filterTarget.val().trim();
            if (target) params.set('target', target);

            const since = $filterSince.val().trim();
            if (since) params.set('since', since);

            const until = $filterUntil.val().trim();
            if (until) params.set('until', until);

            ajaxWithCsrf({ url: '/resources/ajax/audit.php?' + params.toString(), method: 'GET' })
                .done((res) => {
                    renderAuditRows(res.items || []);
                    updatePagination(res.total || 0, res.page || 1, res.per_page || auditPerPage);
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon auditlog niet laden.';
                    showAlert($alert, msg);
                    $tbody.html('<tr><td colspan="5" style="padding:16px; text-align:center; color:#888;">Kon niet laden.</td></tr>');
                });
        };

        const scheduleReload = (delay = 300) => {
            clearTimeout($refresh.data('timer'));
            const timer = setTimeout(loadAudit, delay);
            $refresh.data('timer', timer);
        };

        $refresh.on('click', loadAudit);
        $filterAction.on('input', () => scheduleReload(500));
        $filterActor.on('input', () => scheduleReload(500));
        $filterTarget.on('input', () => scheduleReload(500));
        $filterSince.on('change', loadAudit);
        $filterUntil.on('change', loadAudit);

        $('#audit-export').on('click', () => {
            const params = new URLSearchParams();
            params.set('format', 'csv');
            params.set('page', String(auditPage));
            params.set('per_page', String(auditPerPage));

            const action = $filterAction.val().trim();
            if (action) params.set('action', action);

            const actor = $filterActor.val().trim();
            if (actor) params.set('actor', actor);

            const target = $filterTarget.val().trim();
            if (target) params.set('target', target);

            const since = $filterSince.val().trim();
            if (since) params.set('since', since);

            const until = $filterUntil.val().trim();
            if (until) params.set('until', until);

            window.open('/resources/ajax/audit.php?' + params.toString(), '_blank');
        });

        $pagination.on('click', 'button', function () {
            const newPage = parseInt($(this).data('page'), 10);
            if (Number.isNaN(newPage) || newPage === auditPage) return;
            auditPage = newPage;
            loadAudit();
        });

        loadAudit();
    }

    // ---------- Roles / capabilities ----------
    if ($('#roles-card').length) {
        const $alert = $('#roles-alert');
        const $tbody = $('#roles-table tbody');
        const $newRole = $('#roles-new-name');
        const $newCap = $('#roles-new-cap');

        let rolesConfig = {};

        const getAllCaps = () => {
            const caps = new Set();
            Object.values(rolesConfig).forEach((capsArr) => {
                (capsArr || []).forEach((c) => caps.add(c));
            });
            return Array.from(caps).sort();
        };

        const renderRoles = () => {
            const caps = getAllCaps();

            if (!Object.keys(rolesConfig).length) {
                $tbody.html('<tr><td colspan="3" style="padding:16px; text-align:center; color:#888;">Geen rollen geconfigureerd.</td></tr>');
                return;
            }

            const rows = Object.entries(rolesConfig).map(([role, roleCaps]) => {
                const capCheckboxes = caps
                    .map((cap) => {
                        const checked = (roleCaps || []).includes(cap) ? 'checked' : '';
                        return `<label style="margin-right:10px;"><input type="checkbox" class="role-cap" data-role="${role}" data-cap="${cap}" ${checked} /> ${cap}</label>`;
                    })
                    .join('<br>');

                return `
                    <tr>
                        <td style="padding:10px; border-bottom:1px solid #eee; vertical-align:top;">${role}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee;">${capCheckboxes}</td>
                        <td style="padding:10px; border-bottom:1px solid #eee; white-space:nowrap;"><button class="btn-secondary roles-delete" data-role="${role}">Verwijderen</button></td>
                    </tr>
                `;
            });

            $tbody.html(rows.join(''));
        };

        const loadRoles = () => {
            ajaxWithCsrf({ url: '/resources/ajax/roles.php', method: 'GET' })
                .done((res) => {
                    rolesConfig = res.roles || {};
                    renderRoles();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon rollenconfiguratie niet laden.';
                    showAlert($alert, msg);
                    $tbody.html('<tr><td colspan="3" style="padding:16px; text-align:center; color:#888;">Kon niet laden.</td></tr>');
                });
        };

        const saveRoles = () => {
            ajaxWithCsrf({ url: '/resources/ajax/roles.php', method: 'POST', data: { roles: rolesConfig } })
                .done((res) => {
                    rolesConfig = res.roles || rolesConfig;
                    renderRoles();
                    showAlert($alert, 'Rollenconfiguratie opgeslagen.', 'success');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                    showAlert($alert, msg);
                });
        };

        $('#roles-add').on('click', () => {
            const roleName = $newRole.val().trim();
            const cap = $newCap.val().trim();
            if (!roleName) {
                showAlert($alert, 'Vul een rolnaam in.');
                return;
            }

            if (rolesConfig[roleName]) {
                showAlert($alert, 'Die rol bestaat al.');
                return;
            }

            rolesConfig[roleName] = cap ? [cap] : [];
            $newRole.val('');
            $newCap.val('');
            renderRoles();
        });

        $('#roles-save').on('click', () => {
            saveRoles();
        });

        $tbody.on('change', '.role-cap', function () {
            const $checkbox = $(this);
            const role = $checkbox.data('role');
            const cap = $checkbox.data('cap');

            if (!role || !cap) {
                return;
            }

            const checked = $checkbox.is(':checked');
            const caps = rolesConfig[role] || [];
            if (checked) {
                if (!caps.includes(cap)) {
                    caps.push(cap);
                }
            } else {
                rolesConfig[role] = caps.filter((c) => c !== cap);
            }
        });

        $tbody.on('click', '.roles-delete', function () {
            const role = $(this).data('role');
            if (!role) return;

            if (!confirm(`Weet je zeker dat je de rol "${role}" wilt verwijderen?`)) {
                return;
            }

            delete rolesConfig[role];
            renderRoles();
        });

        loadRoles();
    }

    // ---------- Users ----------
    if ($('#users-tbody').length) {
        const $usersAlert = $('#users-alert');
        const $usersTbody = $('#users-tbody');
        const $usersModal = $('#users-modal');
        const $usersModalTitle = $('#users-modal-title');
        const $usersModalAlert = $('#users-modal-alert');
        const $usersModalName = $('#users-modal-name');
        const $usersModalEmail = $('#users-modal-email');
        const $usersModalLogin = $('#users-modal-login');
        const $usersModalSave = $('#users-modal-save');
        const $usersModalCancel = $('#users-modal-cancel');
        const $usersModalRole = $('#users-modal-role');

        const $usersSearch = $('#users-search');
        const $usersFilterRole = $('#users-filter-role');
        const $usersFilterStatus = $('#users-filter-status');
        const $usersPerPage = $('#users-per-page');
        const $usersPagination = $('#users-pagination');
        const $usersBulkAction = $('#users-bulk-action');
        const $usersBulkApply = $('#users-bulk-apply');
        const $usersSelectAll = $('#users-select-all');

        let usersPage = 1;
        let usersPerPage = parseInt($usersPerPage.val(), 10) || 25;

        const userAlert = (message, type = 'error') => showAlert($usersAlert, message, type);
        const modalAlert = (message, type = 'error') => {
            if (!message) {
                $usersModalAlert.hide();
                return;
            }
            showAlert($usersModalAlert, message, type, 7000);
        };

        const renderStatus = (status) => {
            switch (status) {
                case 1:
                    return '<span style="color:#a05; font-weight:600;">Geblokkeerd</span>';
                case 2:
                    return '<span style="color:#aa0; font-weight:600;">Verwijderd</span>';
                default:
                    return '<span style="color:#1a7; font-weight:600;">Actief</span>';
            }
        };

        const renderActions = (user) => {
            const isBanned = user.user_status === 1;
            const isDeleted = user.user_status === 2;
            const disable = isDeleted ? 'disabled' : '';

            const makeLink = (label, action) =>
                `<a href="#" class="row-action" data-action="${action}" data-id="${user.id}" ${disable}>${label}</a>`;

            const parts = [
                makeLink('Quick edit', 'quick_edit'),
                makeLink('Bewerken', 'edit'),
                makeLink('Stuur reset link', 'send_reset_link'),
                makeLink(isBanned ? 'Deblokkeer' : 'Blokkeer', isBanned ? 'unban' : 'ban'),
                makeLink('Verwijder', 'soft_delete'),
            ];

            return `<span class="row-actions">${parts.join(' | ')}</span>`;
        };

        let activeUserId = null;
        let activeMode = 'create';

        const openModal = (mode, user = {}) => {
            activeMode = mode;
            activeUserId = user.id || null;

            $usersModalTitle.text(mode === 'create' ? 'Nieuwe gebruiker' : 'Gebruiker bewerken');
            modalAlert('', 'success');

            $usersModalName.val(user.display_name || '');
            $usersModalEmail.val(user.user_email || '');
            $usersModalLogin.val(user.user_login || '');
            $usersModalRole.val(user.user_role || 'user');

            $usersModal.css('display', 'flex');
        };

        const closeModal = () => {
            $usersModal.css('display', 'none');
            modalAlert('', 'success');
        };

        const updatePagination = (total, page, perPage) => {
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            usersPage = Math.min(Math.max(1, page), totalPages);

            const pages = [];
            for (let p = 1; p <= totalPages; p += 1) {
                pages.push(p);
            }

            if (!pages.length) {
                $usersPagination.html('');
                return;
            }

            const buttons = pages.map((p) => {
                const active = p === usersPage ? 'background:#333;color:#fff;' : '';
                return `<button data-page="${p}" style="padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer; ${active}">${p}</button>`;
            });

            $usersPagination.html(buttons.join(' '));
        };

        const restoreRow = ($row, updatedData = null) => {
            const original = $row.data('original');
            if (!original) {
                $row.removeClass('quick-editing');
                return;
            }

            if (updatedData) {
                $row.find('td').eq(2).text(updatedData.display_name || '');
                $row.find('td').eq(3).text(updatedData.user_email || '');
                $row.find('td').eq(4).text(updatedData.user_login || '');
                $row.find('td').eq(5).text(updatedData.user_role || '');
            } else {
                $row.find('td').eq(2).html(original.name);
                $row.find('td').eq(3).html(original.email);
                $row.find('td').eq(4).html(original.login);
                $row.find('td').eq(5).html(original.role);
            }

            $row.find('td').eq(8).html(original.actions);
            $row.removeClass('quick-editing');
            $row.removeData('original');
        };

        const loadUsers = () => {
            const params = new URLSearchParams();
            params.set('page', String(usersPage));
            params.set('per_page', String(usersPerPage));

            const search = $usersSearch.val().trim();
            if (search) {
                params.set('q', search);
            }

            const role = $usersFilterRole.val();
            if (role) {
                params.set('role', role);
            }

            const status = $usersFilterStatus.val();
            if (status !== '') {
                params.set('status', status);
            }

            ajaxWithCsrf({ url: '/api/users?' + params.toString(), method: 'GET' })
                .done((res) => {
                    const users = res.users || [];
                    if (!users.length) {
                        $usersTbody.html('<tr><td colspan="8" style="padding:16px; text-align:center; color:#888;">Geen gebruikers gevonden.</td></tr>');
                        updatePagination(0, 1, usersPerPage);
                        return;
                    }

                    const rows = users.map((user) => {
                        return '<tr>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee; text-align:center;"><input type="checkbox" class="users-select" data-id="' + user.id + '" /></td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.id || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.display_name || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_email || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_login || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_role || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + renderStatus(user.user_status) + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_registered || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + renderActions(user) + '</td>' +
                            '</tr>';
                    });

                    $usersTbody.html(rows.join(''));
                    updatePagination(res.total || 0, res.page || 1, res.per_page || usersPerPage);
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon gebruikers niet laden.';
                    userAlert(msg);
                    $usersTbody.html('<tr><td colspan="8" style="padding:16px; text-align:center; color:#888;">Kon niet laden.</td></tr>');
                });
        };

        const saveUser = () => {
            const data = {
                display_name: $usersModalName.val().trim(),
                user_email: $usersModalEmail.val().trim(),
                user_login: $usersModalLogin.val().trim(),
                user_role: $usersModalRole.val(),
            };

            if (!data.user_login || !data.user_email) {
                modalAlert('Gebruikersnaam en e-mail zijn verplicht.');
                return;
            }

            data.action = activeMode === 'create' ? 'create' : 'update';
            if (activeMode === 'edit') {
                data.id = activeUserId;
            }

            const method = activeMode === 'create' ? 'POST' : 'PATCH';

            ajaxWithCsrf({
                url: '/api/users',
                method,
                data,
            })
                .done(() => {
                    const successMessage = activeMode === 'create' ? 'Gebruiker aangemaakt.' : 'Gebruiker bijgewerkt.';

                    closeModal();
                    userAlert(successMessage, 'success');


                    loadUsers();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                    modalAlert(msg);
                });
        };

        $('#users-add').on('click', () => openModal('create'));
        $usersModalCancel.on('click', closeModal);
        $usersModalSave.on('click', saveUser);

        const refreshUsers = () => {
            usersPage = 1;
            loadUsers();
        };

        $usersSearch.on('input', () => {
            clearTimeout($usersSearch.data('timer'));
            const timer = setTimeout(refreshUsers, 300);
            $usersSearch.data('timer', timer);
        });

        $usersFilterRole.on('change', refreshUsers);
        $usersFilterStatus.on('change', refreshUsers);

        $usersPerPage.on('change', () => {
            usersPerPage = parseInt($usersPerPage.val(), 10) || 25;
            refreshUsers();
        });

        $usersSelectAll.on('change', function () {
            const checked = $(this).is(':checked');
            $usersTbody.find('.users-select').prop('checked', checked);
        });

        const getSelectedUserIds = () => {
            return $usersTbody
                .find('.users-select:checked')
                .map(function () {
                    return $(this).data('id');
                })
                .get();
        };

        $usersBulkApply.on('click', () => {
            const action = $usersBulkAction.val();
            const ids = getSelectedUserIds();

            if (!action) {
                userAlert('Kies eerst een bulkactie.');
                return;
            }
            if (!ids.length) {
                userAlert('Selecteer eerst gebruikers.');
                return;
            }

            const mapping = {
                bulk_delete: 'soft_delete',
                bulk_ban: 'ban',
                bulk_unban: 'unban',
            };

            const mappedAction = mapping[action];
            if (!mappedAction) {
                userAlert('Onbekende actie.');
                return;
            }

            const promises = ids.map((id) => {
                return ajaxWithCsrf({
                    url: '/api/users',
                    method: 'PATCH',
                    data: { action: mappedAction, id },
                });
            });

            Promise.allSettled(promises).then((results) => {
                const failed = results.filter((r) => r.status === 'rejected');
                if (failed.length) {
                    userAlert('Sommige acties zijn niet gelukt. Vernieuw de pagina.', 'error');
                } else {
                    userAlert('Bulkactie voltooid.', 'success');
                }
                $usersSelectAll.prop('checked', false);
                loadUsers();
            });
        });

        $usersPagination.on('click', 'button', function () {
            const newPage = parseInt($(this).data('page'), 10);
            if (Number.isNaN(newPage) || newPage === usersPage) {
                return;
            }
            usersPage = newPage;
            loadUsers();
        });

        // Enable password toggles on dashboard load
        enablePasswordToggle($(document));

        $usersTbody.on('click', '[data-action]', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const action = $btn.data('action');
            const userId = $btn.data('id');

            if (action === 'quick_edit') {
                const $row = $btn.closest('tr');

                // If already editing, cancel.
                if ($row.hasClass('quick-editing')) {
                    restoreRow($row);
                    return;
                }

                // Close any other open quick-edit row first.
                $usersTbody.find('.quick-editing').each(function () {
                    restoreRow($(this));
                });

                const displayName = $row.find('td').eq(2).text().trim();
                const email = $row.find('td').eq(3).text().trim();
                const login = $row.find('td').eq(4).text().trim();
                const role = $row.find('td').eq(5).text().trim();

                // Store original HTML so we can restore on cancel.
                $row.data('original', {
                    name: $row.find('td').eq(2).html(),
                    email: $row.find('td').eq(3).html(),
                    login: $row.find('td').eq(4).html(),
                    role: $row.find('td').eq(5).html(),
                    actions: $row.find('td').eq(8).html(),
                });

                $row.addClass('quick-editing');

                $row.find('td').eq(2).html(`<input class="qe-name" type="text" value="${displayName}" style="width:100%;" />`);
                $row.find('td').eq(3).html(`<input class="qe-email" type="email" value="${email}" style="width:100%;" />`);
                $row.find('td').eq(4).html(`<input class="qe-login" type="text" value="${login}" style="width:100%;" />`);
                $row.find('td').eq(5).html(`
                    <select class="qe-role" style="width:100%;">
                        <option value="user">Gebruiker</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                `);
                $row.find('td').eq(5).find('select').val(role);

                $row.find('td').eq(8).html(
                    `<button class="btn-primary qe-save" style="margin-right:6px;">Opslaan</button>` +
                    `<button class="btn-secondary qe-cancel">Annuleren</button>`
                );

                $row.find('.qe-cancel').on('click', () => restoreRow($row));
                $row.find('.qe-save').on('click', () => {
                    const data = {
                        action: 'update',
                        id: userId,
                        display_name: $row.find('.qe-name').val().trim(),
                        user_email: $row.find('.qe-email').val().trim(),
                        user_login: $row.find('.qe-login').val().trim(),
                        user_role: $row.find('.qe-role').val(),
                    };

                    ajaxWithCsrf({ url: '/api/users', method: 'PATCH', data })
                        .done(() => {
                            userAlert('Gebruiker bijgewerkt.', 'success');
                            restoreRow($row, data);
                        })
                        .fail((xhr) => {
                            const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                            userAlert(msg);
                        });
                });

                return;
            }

            if (!userId) {
                return;
            }

            if (action === 'edit') {
                // Load selected user from the row
                const $row = $btn.closest('tr');
                const user = {
                    id: userId,
                    display_name: $row.find('td').eq(1).text().trim(),
                    user_email: $row.find('td').eq(2).text().trim(),
                    user_login: $row.find('td').eq(3).text().trim(),
                    user_role: $row.find('td').eq(4).text().trim() || 'user',
                };
                openModal('edit', user);
                return;
            }

            if (action === 'send_reset_link') {
                const $row = $btn.closest('tr');
                const email = $row.find('td').eq(2).text().trim();
                const login = $row.find('td').eq(3).text().trim();

                const username = email || login;
                if (!username) {
                    userAlert('Geen e-mailadres of gebruikersnaam gevonden.');
                    return;
                }

                ajaxWithCsrf({
                    url: '/resources/ajax/forgot-password.php',
                    method: 'POST',
                    data: {
                        action: 'request',
                        username,
                    },
                })
                    .done(() => {
                        userAlert('Resetlink is verzonden (indien e-mail bestaat).', 'success');
                    })
                    .fail((xhr) => {
                        const msg = xhr.responseJSON?.error || 'Actie mislukt.';
                        userAlert(msg);
                    });

                return;
            }


            if (!confirm('Weet je het zeker?')) {
                return;
            }

            ajaxWithCsrf({
                url: '/api/users',
                method: 'PATCH',
                data: { action, id: userId },
            })
                .done(() => {
                    userAlert('Actie voltooid.', 'success');
                    loadUsers();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Actie mislukt.';
                    userAlert(msg);
                });
        });

        loadUsers();
    }

    // ---------- Posts ----------
    if ($('#posts-tbody').length) {
        const $postsAlert = $('#posts-alert');
        const $postsTbody = $('#posts-tbody');
        const $postsAdd = $('#posts-add');
        const postType = window.MOL_CURRENT_POST_TYPE || '';
        const postTypeConfig = (window.MOL_POST_TYPES || {})[postType] || {};
        const postTypeSupports = Array.isArray(postTypeConfig.supports) ? postTypeConfig.supports : ['title', 'content'];
        const postTypeTaxonomies = Array.isArray(postTypeConfig.taxonomies) ? postTypeConfig.taxonomies : [];

        const $postsModal = $('#posts-modal');
        const $postsModalTitle = $('#posts-modal-title');
        const $postsModalAlert = $('#posts-modal-alert');
        const $postsModalTitleInput = $('#posts-modal-title-input');
        const $postsModalBlockList = $('#posts-modal-block-list');
        const $postsModalBlockType = $('#posts-modal-block-type');
        const $postsModalAddBlock = $('#posts-modal-add-block');
        const $postsModalType = $('#posts-modal-type');
        const $postsModalStatus = $('#posts-modal-status');
        const $postsModalDate = $('#posts-modal-date');
        const $postsModalAuthor = $('#posts-modal-author');
        const $postsModalExcerpt = $('#posts-modal-excerpt');
        const $postsModalMeta = $('#posts-modal-meta');
        const $postsModalTaxonomies = $('#posts-modal-taxonomies');
        const $postsModalRowDate = $('#posts-modal-row-date');
        const $postsModalRowAuthor = $('#posts-modal-row-author');
        const $postsModalRowExcerpt = $('#posts-modal-row-excerpt');
        const $postsModalRowMeta = $('#posts-modal-row-meta');
        const $postsModalRowTaxes = $('#posts-modal-row-taxes');
        const $postsModalCancel = $('#posts-modal-cancel');
        const $postsModalSave = $('#posts-modal-save');

        const buildPostsTableHeader = () => {
            const $thead = $('#posts-thead');
            const cols = [];
            cols.push({ key: 'id', label: 'ID' });
            cols.push({ key: 'title', label: 'Titel' });
            if (postTypeSupports.includes('status')) {
                cols.push({ key: 'status', label: 'Status' });
            }
            if (postTypeSupports.includes('date')) {
                cols.push({ key: 'date', label: 'Datum' });
            }
            if (postTypeSupports.includes('author')) {
                cols.push({ key: 'author', label: 'Auteur' });
            }
            if (postTypeSupports.includes('excerpt')) {
                cols.push({ key: 'excerpt', label: 'Excerpt' });
            }
            if (postTypeSupports.includes('custom-fields')) {
                cols.push({ key: 'meta', label: 'Meta' });
            }
            cols.push({ key: 'actions', label: 'Acties' });

            const html = '<tr>' + cols.map((c) => '<th>' + c.label + '</th>').join('') + '</tr>';
            $thead.html(html);
        };

        const renderPostRow = (post) => {
            const cells = [];
            cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + (post.ID || '') + '</td>');
            cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + (post.post_title || '') + '</td>');

            if (postTypeSupports.includes('status')) {
                cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + renderStatus(post.post_status) + '</td>');
            }

            if (postTypeSupports.includes('date')) {
                cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + (post.post_date || '') + '</td>');
            }

            if (postTypeSupports.includes('author')) {
                cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + (post.post_author || '') + '</td>');
            }

            if (postTypeSupports.includes('excerpt')) {
                const excerpt = (post.post_excerpt || '').substring(0, 80);
                cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + excerpt + '</td>');
            }

            if (postTypeSupports.includes('custom-fields')) {
                const metaCount = post.meta ? Object.keys(post.meta).length : 0;
                cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + metaCount + '</td>');
            }

            cells.push('<td style="padding:12px; border-bottom:1px solid #eee;">' + renderActions(post) + '</td>');

            return '<tr data-id="' + post.ID + '" data-title="' + (post.post_title || '').replace(/"/g, '&quot;') + '" data-content="' + (post.post_content || '').replace(/"/g, '&quot;') + '" data-status="' + (post.post_status || '') + '" data-type="' + (post.post_type || '') + '">' + cells.join('') + '</tr>';
        };

        let activePostId = null;
        let activeMode = 'create';

        const postAlert = (message, type = 'error') => showAlert($postsAlert, message, type);
        const modalAlert = (message, type = 'error') => {
            if (!message) {
                $postsModalAlert.hide();
                return;
            }
            showAlert($postsModalAlert, message, type, 7000);
        };

        const renderStatus = (status) => {
            if (status === 'published') {
                return '<span style="color:#1a7; font-weight:600;">Gepubliceerd</span>';
            }
            return '<span style="color:#888; font-weight:600;">Concept</span>';
        };

        const renderActions = (post) => {
            return '<span class="row-actions">' +
                '<a href="#" class="row-action" data-action="edit" data-id="' + post.ID + '">Bewerken</a> | ' +
                '<a href="#" class="row-action" data-action="delete" data-id="' + post.ID + '">Verwijderen</a>' +
                '</span>';
        };

        let postBlocks = [];

        const escapeHtml = (str) => {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const parseBlocks = (content) => {
            if (!content) {
                return [{ type: 'paragraph', text: '' }];
            }
            try {
                const parsed = JSON.parse(content);
                if (Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (e) {
                // Ignore parse errors; fall back to plain text.
            }
            return [{ type: 'paragraph', text: content }];
        };

        const renderBlocks = () => {
            $postsModalBlockList.empty();
            postBlocks.forEach((block, idx) => {
                const $item = $('<div class="block-item"></div>').attr('data-type', block.type || 'paragraph').attr('data-index', idx);
                const typeLabel = block.type === 'heading' ? 'Kop' : block.type === 'image' ? 'Afbeelding' : 'Paragraaf';
                const $header = $('<div class="block-item-header"></div>');
                $header.append(`<div class="block-item-title">${typeLabel}</div>`);
                const $actions = $('<div class="block-item-actions"></div>');
                $actions.append('<button type="button" class="btn-secondary btn-small block-move" data-dir="up">↑</button>');
                $actions.append('<button type="button" class="btn-secondary btn-small block-move" data-dir="down">↓</button>');
                $actions.append('<button type="button" class="btn-secondary btn-small block-delete">✕</button>');
                $header.append($actions);
                $item.append($header);

                if (block.type === 'image') {
                    $item.append(`<input type="text" class="block-input block-input-url" placeholder="Afbeelding URL" value="${escapeHtml(block.url || '')}" />`);
                    $item.append(`<input type="text" class="block-input block-input-alt" placeholder="Alt tekst" value="${escapeHtml(block.alt || '')}" />`);
                } else {
                    $item.append(`<textarea class="block-input" rows="4" placeholder="Tekst...">${escapeHtml(block.text || '')}</textarea>`);
                }

                $postsModalBlockList.append($item);
            });
        };

        const moveBlock = (from, to) => {
            if (from < 0 || to < 0 || from >= postBlocks.length || to >= postBlocks.length) return;
            const [moved] = postBlocks.splice(from, 1);
            postBlocks.splice(to, 0, moved);
            renderBlocks();
        };

        const deleteBlock = (index) => {
            if (index < 0 || index >= postBlocks.length) return;
            postBlocks.splice(index, 1);
            renderBlocks();
        };

        const addBlock = (type) => {
            const block = { type };
            if (type === 'image') {
                block.url = '';
                block.alt = '';
            } else {
                block.text = '';
            }
            postBlocks.push(block);
            renderBlocks();
        };

        const openModal = (mode, post = {}) => {
            activeMode = mode;
            activePostId = post.ID || null;

            $postsModalTitle.text(mode === 'create' ? 'Nieuw bericht' : 'Bericht bewerken');
            modalAlert('', 'success');

            $postsModalTitleInput.val(post.post_title || '');
            postBlocks = parseBlocks(post.post_content || '');
            renderBlocks();
            $postsModalStatus.val(post.post_status || 'draft');

            // Show/hide fields based on post type supports
            const hasDate = postTypeSupports.includes('date');
            const hasAuthor = postTypeSupports.includes('author');
            const hasExcerpt = postTypeSupports.includes('excerpt');
            const hasMeta = postTypeSupports.includes('custom-fields');

            $postsModalRowDate.toggle(hasDate);
            $postsModalRowAuthor.toggle(hasAuthor);
            $postsModalRowExcerpt.toggle(hasExcerpt);
            $postsModalRowMeta.toggle(hasMeta);

            if (hasDate) {
                const date = post.post_date ? new Date(post.post_date) : new Date();
                $postsModalDate.val(date.toISOString().slice(0, 16));
            }
            if (hasAuthor) {
                $postsModalAuthor.val(post.post_author || '');
            }
            if (hasExcerpt) {
                $postsModalExcerpt.val(post.post_excerpt || '');
            }
            if (hasMeta) {
                const meta = post.meta || {};
                const lines = Object.entries(meta).map(([k,v]) => `${k}=${v}`);
                $postsModalMeta.val(lines.join('\n'));
            }

            // Taxonomies
            const hasTax = Array.isArray(postTypeTaxonomies) && postTypeTaxonomies.length > 0;
            $postsModalRowTaxes.toggle(hasTax);
            $postsModalTaxonomies.empty();

            if (hasTax) {
                const selectedTerms = (post.terms || []).reduce((acc, term) => {
                    if (!acc[term.taxonomy]) acc[term.taxonomy] = [];
                    acc[term.taxonomy].push(term.term_id);
                    return acc;
                }, {});

                postTypeTaxonomies.forEach((taxonomy) => {
                    const wrapper = $('<div style="margin-bottom:12px;"></div>');
                    wrapper.append(`<div style="font-weight:600; margin-bottom:4px;">${taxonomy}</div>`);
                    const list = $('<div style="display:flex; flex-wrap:wrap; gap:8px;"></div>');

                    wrapper.append(list);
                    $postsModalTaxonomies.append(wrapper);

                    ajaxWithCsrf({ url: '/api/taxonomies?taxonomy=' + encodeURIComponent(taxonomy), method: 'GET' })
                        .done((terms) => {
                            if (!Array.isArray(terms)) return;
                            terms.forEach((term) => {
                                const termId = term.term_id;
                                const checked = (selectedTerms[taxonomy] || []).includes(termId);
                                const $label = $(
                                    `<label style="display:inline-flex; align-items:center; gap:4px;">
                                        <input type="checkbox" value="${termId}" data-taxonomy="${taxonomy}" ${checked ? 'checked' : ''} />
                                        ${term.name}
                                    </label>`
                                );
                                list.append($label);
                            });
                        });
                });
            }

            if (post.post_type) {
                $postsModalType.val(post.post_type);
            } else if (postType) {
                $postsModalType.val(postType);
            }

            // Prevent changing post type when viewing a specific type.
            if (postType) {
                $postsModalType.prop('disabled', true);
            } else {
                $postsModalType.prop('disabled', false);
            }

            $postsModal.css('display', 'flex');
        };

        const closeModal = () => {
            $postsModal.css('display', 'none');
            modalAlert('', 'success');
        };

        // Block editor interactions
        $postsModalAddBlock.on('click', (e) => {
            e.preventDefault();
            addBlock($postsModalBlockType.val());
        });

        $postsModalBlockList.on('click', '.block-move', function () {
            const $btn = $(this);
            const dir = $btn.data('dir');
            const $item = $btn.closest('.block-item');
            const index = parseInt($item.attr('data-index'), 10);
            if (Number.isNaN(index)) return;
            if (dir === 'up') {
                moveBlock(index, index - 1);
            } else {
                moveBlock(index, index + 1);
            }
        });

        $postsModalBlockList.on('click', '.block-delete', function () {
            const $item = $(this).closest('.block-item');
            const index = parseInt($item.attr('data-index'), 10);
            if (Number.isNaN(index)) return;
            deleteBlock(index);
        });

        $postsModalBlockList.on('input', '.block-input', function () {
            const $input = $(this);
            const $item = $input.closest('.block-item');
            const index = parseInt($item.attr('data-index'), 10);
            if (Number.isNaN(index)) return;
            const block = postBlocks[index];
            if (!block) return;

            if ($input.hasClass('block-input-url')) {
                block.url = $input.val();
            } else if ($input.hasClass('block-input-alt')) {
                block.alt = $input.val();
            } else {
                block.text = $input.val();
            }
        });

        const loadPosts = () => {
            const params = new URLSearchParams();
            if (postType) {
                params.set('post_type', postType);
            }

            ajaxWithCsrf({ url: '/api/posts?' + params.toString(), method: 'GET' })
                .done((res) => {
                    const posts = Array.isArray(res) ? res : [];
                    if (!posts.length) {
                        const colspan = 2 + (postTypeSupports.includes('status') ? 1 : 0) + (postTypeSupports.includes('date') ? 1 : 0) + (postTypeSupports.includes('author') ? 1 : 0) + (postTypeSupports.includes('excerpt') ? 1 : 0) + (postTypeSupports.includes('custom-fields') ? 1 : 0) + 1;
                        $postsTbody.html('<tr><td colspan="' + colspan + '" style="padding:16px; text-align:center; color:#888;">Geen berichten gevonden.</td></tr>');
                        return;
                    }

                    buildPostsTableHeader();

                    const rows = posts.map(renderPostRow);
                    $postsTbody.html(rows.join(''));
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon berichten niet laden.';
                    postAlert(msg);
                    $postsTbody.html('<tr><td colspan="6" style="padding:16px; text-align:center; color:#888;">Kon niet laden.</td></tr>');
                });
        };

        const savePost = () => {
            const normalizedBlocks = postBlocks
                .map((b) => ({ ...b }))
                .filter((b) => {
                    if (b.type === 'image') {
                        return (b.url || '').trim() !== '';
                    }
                    return (b.text || '').trim() !== '';
                });

            if (!normalizedBlocks.length) {
                normalizedBlocks.push({ type: 'paragraph', text: '' });
            }

            const data = {
                post_title: $postsModalTitleInput.val().trim(),
                post_blocks: normalizedBlocks,
                post_status: $postsModalStatus.val(),
                post_type: $postsModalType.val() || postType,
            };

            if (postTypeSupports.includes('date')) {
                const date = $postsModalDate.val();
                if (date) {
                    data.post_date = date;
                }
            }
            if (postTypeSupports.includes('author')) {
                const author = $postsModalAuthor.val().trim();
                if (author) {
                    data.post_author = author;
                }
            }
            if (postTypeSupports.includes('excerpt')) {
                const excerpt = $postsModalExcerpt.val().trim();
                if (excerpt) {
                    data.post_excerpt = excerpt;
                }
            }
            if (postTypeSupports.includes('custom-fields')) {
                const raw = $postsModalMeta.val().trim();
                if (raw) {
                    const meta = {};
                    raw.split(/\r?\n/).forEach((line) => {
                        const [key, ...rest] = line.split('=');
                        if (!key) return;
                        meta[key.trim()] = rest.join('=').trim();
                    });
                    data.meta = meta;
                }
            }

            if (Array.isArray(postTypeTaxonomies) && postTypeTaxonomies.length) {
                const terms = {};
                $postsModalTaxonomies.find('input[type="checkbox"]:checked').each(function () {
                    const $chk = $(this);
                    const taxonomy = $chk.data('taxonomy');
                    const tid = parseInt($chk.val(), 10);
                    if (!taxonomy) return;
                    if (!terms[taxonomy]) terms[taxonomy] = [];
                    terms[taxonomy].push(tid);
                });
                if (Object.keys(terms).length) {
                    data.terms = terms;
                }
            }

            if (!data.post_title) {
                modalAlert('Titel is verplicht.');
                return;
            }

            const method = activeMode === 'create' ? 'POST' : 'PATCH';
            const url = activeMode === 'create' ? '/api/posts' : '/api/posts/' + activePostId;

            ajaxWithCsrf({ url, method, data })
                .done(() => {
                    closeModal();
                    postAlert(activeMode === 'create' ? 'Bericht aangemaakt.' : 'Bericht bijgewerkt.', 'success');
                    loadPosts();
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                    modalAlert(msg);
                });
        };

        $postsAdd.on('click', () => openModal('create'));
        $postsModalCancel.on('click', closeModal);
        $postsModalSave.on('click', savePost);


        $postsTbody.on('click', '[data-action]', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const action = $btn.data('action');
            const postId = $btn.data('id');

            if (!postId) {
                return;
            }

            if (action === 'edit') {
                ajaxWithCsrf({ url: '/api/posts/' + postId, method: 'GET' })
                    .done((post) => {
                        openModal('edit', post);
                    })
                    .fail((xhr) => {
                        const msg = xhr.responseJSON?.error || 'Kon bericht niet laden.';
                        postAlert(msg);
                    });
                return;
            }

            if (action === 'delete') {
                if (!confirm('Weet je zeker dat je dit bericht wilt verwijderen?')) {
                    return;
                }

                ajaxWithCsrf({ url: '/api/posts/' + postId, method: 'DELETE' })
                    .done(() => {
                        postAlert('Bericht verwijderd.', 'success');
                        loadPosts();
                    })
                    .fail((xhr) => {
                        const msg = xhr.responseJSON?.error || 'Verwijderen mislukt.';
                        postAlert(msg);
                    });
                return;
            }
        });

        loadPosts();
    }

});
