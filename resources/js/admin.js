$(function () {
    // ---------- Helpers ----------
    const getCsrfToken = () =>
        $.getJSON('/resources/ajax/get-nonce.php').then((res) => res.nonce || '');

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

    // ---------- Login ----------
    if ($('#loginForm').length) {
        const $form = $('#loginForm');
        const $submit = $('#submitBtn');

        const startLogin = () => {
            $submit.addClass('loading in-progress');
            $submit.text('Bezig met inloggen...');
        };

        const endLogin = () => {
            $submit.removeClass('loading in-progress');
            $submit.text('Inloggen');
        };

        $form.on('submit', function (event) {
            event.preventDefault();

            const data = {
                action: 'login',
                user_login: $('#username').val(),
                user_password: $('#password').val(),
                remember: $('#remember').is(':checked'),
            };

            startLogin();

            getCsrfToken().then((csrfToken) => {
                return $.ajax({
                    url: '/resources/ajax/auth.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ ...data, _nonce_action: 'global_csrf', _nonce: csrfToken }),
                    dataType: 'json',
                    headers: {
                        'X-CSRF-Token': csrfToken,
                        'X-CSRF-Action': 'global_csrf',
                    },
                    xhrFields: { withCredentials: true },
                });
            })
            .done(() => {
                window.location.href = '/dashboard';
            })
            .fail((xhr) => {
                const msg = xhr.responseJSON?.error || 'Inloggen mislukt';
                let $error = $('.error-message');

                if (!$error.length) {
                    $error = $('<div class="error-message"></div>');
                    $('.form-container').prepend($error);
                }

                $error.text(msg);
            })
            .always(endLogin);
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                endLogin();
            }
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

        const loadAccount = () => {
            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'GET' })
                .done((res) => {
                    fillForm(res.user || {});
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon account niet laden.';
                    showAlert($alert, msg);
                });
        };

        const saveAccount = () => {
            const currentPassword = $('#account_current_password').val().trim();
            const newPassword = $('#account_new_password').val().trim();
            const confirmPassword = $('#account_new_password_confirm').val().trim();

            if (newPassword && newPassword !== confirmPassword) {
                showAlert($alert, 'Nieuw wachtwoord en bevestiging komen niet overeen.');
                return;
            }

            const data = {
                display_name: $('#account_display_name').val(),
                user_email: $('#account_email').val(),
                user_login: $('#account_login').val(),
                user_url: $('#account_url').val(),
            };

            if (currentPassword && newPassword) {
                data.current_password = currentPassword;
                data.new_password = newPassword;
            }

            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'PUT', data })
                .done(() => {
                    showAlert($alert, 'Accountgegevens opgeslagen.', 'success');
                    $('#account_current_password, #account_new_password, #account_new_password_confirm').val('');
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Opslaan mislukt.';
                    showAlert($alert, msg);
                });
        };

        $('#account-save').on('click', saveAccount);
        loadAccount();
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
        const $usersModalPassword = $('#users-modal-password');
        const $usersModalSave = $('#users-modal-save');
        const $usersModalCancel = $('#users-modal-cancel');

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

            const editButton = `<button class="btn-secondary" ${disable} data-action="edit" data-id="${user.id}" style="margin-right:6px;">Bewerken</button>`;
            const resetPwButton = `<button class="btn-secondary" ${disable} data-action="reset_password" data-id="${user.id}" style="margin-right:6px;">Reset wachtwoord</button>`;
            const banButton = `<button class="btn-secondary" ${disable} data-action="${isBanned ? 'unban' : 'ban'}" data-id="${user.id}" style="margin-right:6px;">${isBanned ? 'Deblokkeer' : 'Blokkeer'}</button>`;
            const deleteButton = `<button class="btn-secondary" ${disable} data-action="soft_delete" data-id="${user.id}">Verwijder</button>`;

            return editButton + resetPwButton + banButton + deleteButton;
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
            $usersModalPassword.val('');

            $usersModal.css('display', 'flex');
        };

        const closeModal = () => {
            $usersModal.css('display', 'none');
            modalAlert('', 'success');
        };

        const loadUsers = () => {
            ajaxWithCsrf({ url: '/api/users', method: 'GET' })
                .done((res) => {
                    const users = res.users || [];
                    if (!users.length) {
                        $usersTbody.html('<tr><td colspan="7" style="padding:16px; text-align:center; color:#888;">Geen gebruikers gevonden.</td></tr>');
                        return;
                    }

                    const rows = users.map((user) => {
                        return '<tr>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.id || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.display_name || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_email || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_login || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + renderStatus(user.user_status) + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + (user.user_registered || '') + '</td>' +
                            '<td style="padding:12px; border-bottom:1px solid #eee;">' + renderActions(user) + '</td>' +
                            '</tr>';
                    });

                    $usersTbody.html(rows.join(''));
                })
                .fail((xhr) => {
                    const msg = xhr.responseJSON?.error || 'Kon gebruikers niet laden.';
                    userAlert(msg);
                    $usersTbody.html('<tr><td colspan="7" style="padding:16px; text-align:center; color:#888;">Kon niet laden.</td></tr>');
                });
        };

        const saveUser = () => {
            const data = {
                display_name: $usersModalName.val().trim(),
                user_email: $usersModalEmail.val().trim(),
                user_login: $usersModalLogin.val().trim(),
            };

            const password = $usersModalPassword.val().trim();

            if (!data.user_login || !data.user_email) {
                modalAlert('Gebruikersnaam en e-mail zijn verplicht.');
                return;
            }

            if (activeMode === 'create') {
                if (!password) {
                    modalAlert('Wachtwoord is verplicht.');
                    return;
                }
                data.user_pass = password;
                data.action = 'create';
            } else {
                data.action = 'update';
                data.id = activeUserId;
                if (password) {
                    data.new_password = password;
                }
            }

            const method = activeMode === 'create' ? 'POST' : 'PATCH';

            ajaxWithCsrf({
                url: '/api/users',
                method,
                data,
            })
                .done(() => {
                    closeModal();
                    userAlert(activeMode === 'create' ? 'Gebruiker aangemaakt.' : 'Gebruiker bijgewerkt.', 'success');
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

        $usersTbody.on('click', 'button[data-action]', function () {
            const $btn = $(this);
            const action = $btn.data('action');
            const userId = $btn.data('id');

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
                };
                openModal('edit', user);
                return;
            }

            if (action === 'reset_password') {
                const newPassword = prompt('Nieuw wachtwoord invoeren:');
                if (!newPassword) {
                    return;
                }

                ajaxWithCsrf({
                    url: '/api/users',
                    method: 'PATCH',
                    data: { action: 'reset_password', id: userId, new_password: newPassword },
                })
                    .done(() => {
                        userAlert('Wachtwoord is gereset.', 'success');
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
});
