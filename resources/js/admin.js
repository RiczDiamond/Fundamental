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

        // Ensure password visibility toggle works on the login screen
        enablePasswordToggle($(document));

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

        const loadAccount = () => {
            ajaxWithCsrf({ url: '/resources/ajax/account.php', method: 'GET' })
                .done((res) => {
                    fillForm(res.user || {});
                    renderSessions(res.sessions || [], res.current_session);
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
});
