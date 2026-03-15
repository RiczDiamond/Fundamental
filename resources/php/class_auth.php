<?php

    declare(strict_types=1);

    class Auth
    {
        private $link;
        private Account $account;
        private Session $session;

        public function __construct($link, Session $session = null)
        {
            $this->link = $link;
            $this->account = new Account($link);
            $this->session = $session ?? new Session($link);
        }

        public function login(string $login, string $password, bool $remember = false)
        {
            if ($login === '' || $password === '') {
                return false;
            }

            $user = filter_var($login, FILTER_VALIDATE_EMAIL)
                ? $this->account->get_user_by_email($login)
                : $this->account->get_user_by_login($login);

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (mol_is_ip_blocked($ip)) {
                return 'ip_blocked';
            }

            if (!$user) {
                return false;
            }

            $userId = (int) ($user['id'] ?? 0);

            // Lockout after too many failed logins.
            $lockedUntil = (int) $this->account->get_user_meta($userId, 'login_locked_until');
            if ($lockedUntil > time()) {
                return 'locked';
            }

            $hash = $user['user_pass'] ?? '';
            if (!$this->verify_password($password, $hash)) {
                // Track failed attempts in usermeta.
                $failedSince = (int) $this->account->get_user_meta($userId, 'login_failed_since');
                $failedCount = (int) $this->account->get_user_meta($userId, 'login_failed_count');

                $now = time();
                $window = 60 * 15; // 15 minutes
                $maxFailures = 5;

                if ($failedSince === 0 || $now - $failedSince > $window) {
                    $failedSince = $now;
                    $failedCount = 0;
                }

                $failedCount++;
                $this->account->set_user_meta($userId, 'login_failed_since', (string) $failedSince);
                $this->account->set_user_meta($userId, 'login_failed_count', (string) $failedCount);

                if ($failedCount >= $maxFailures) {
                    $lockedUntil = $now + (60 * 15); // lock for 15 minutes
                    $this->account->set_user_meta($userId, 'login_locked_until', (string) $lockedUntil);
                    mol_audit($userId, $userId, 'account_locked', ['locked_until' => date('c', $lockedUntil)]);

                    // Block IP for 1 hour after lockout
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    if ($ip) {
                        mol_block_ip($ip, 'Too many failed logins', $now + (60 * 60));
                    }

                    return 'locked';
                }

                return false;
            }

            // Clear failure count on successful authentication
            $this->account->delete_user_meta($userId, 'login_failed_since');
            $this->account->delete_user_meta($userId, 'login_failed_count');
            $this->account->delete_user_meta($userId, 'login_locked_until');

            // 2FA support removed: always complete login immediately.
            // Any stored 2FA state is cleared to avoid leftover session values.
            $this->session->delete('two_factor_user_id');
            $this->session->delete('two_factor_remember');

            $this->completeLogin((int) $user['id'], $remember);
            return true;
        }

        private function completeLogin(int $userId, bool $remember): void
        {
            $this->session->start();
            $this->session->regenerate(true);
            $this->session->set('user_id', $userId);
            $this->session->set('logged_in_at', time());

            // Track active session for logout-on-all-devices.
            $this->register_session($userId);

            // Track last login for audit/user overview.
            $this->account->set_last_login($userId, date('Y-m-d H:i:s'));

            if ($remember) {
                $lifetime = time() + (60 * 60 * 24 * 30);
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                $token = bin2hex(random_bytes(32));
                $tokenHash = password_hash($token, PASSWORD_DEFAULT);

                // Store hashed token in usermeta for persistence.
                $this->account->set_user_meta($userId, 'remember_token', json_encode([
                    'hash' => $tokenHash,
                    'expires' => $lifetime,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ], JSON_THROW_ON_ERROR));

                // Persist it both as a cookie and in usermeta (for recovery/reuse).
                mol_cookie_persist($userId, 'remember_me', $token, $lifetime);
                mol_cookie()->set('remember_me', $token, $lifetime, ['secure' => $secure]);
            }
        }

        private function session_id(): string
        {
            return (string) $this->session->id();
        }

        private function register_session(int $userId): void
        {
            $sid = $this->session_id();
            if (!$sid) {
                return;
            }

            $expires = time() + (60 * 60 * 24 * 30); // 30 days
            $metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sid);
            $metaValue = json_encode([
                'created_at' => date('c'),
                'expires_at' => $expires,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ], JSON_THROW_ON_ERROR);

            $this->account->set_user_meta($userId, $metaKey, $metaValue);
        }

        private function unregister_session(int $userId, string $sessionId): void
        {
            $metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sessionId);
            $this->account->delete_user_meta($userId, $metaKey);
        }

        public function logoutAllSessions(int $userId): void
        {
            // Remove all session entries for this user.
            $this->account->delete_user_meta_like($userId, 'session_%');
        }

        public function refreshSession(): void
        {
            $userId = $this->id();
            if (!$userId) {
                return;
            }
            $this->register_session($userId);
        }

        public function logout(): void
        {
            $userId = $this->session->get('user_id');
            if ($userId) {
                $this->unregister_session((int) $userId, $this->session_id());
                $this->account->delete_user_meta((int) $userId, 'remember_token');
                mol_cookie()->delete('remember_me');
            }

            $this->session->destroy();
        }

        public function check(): bool
        {
            $this->session->start();

            $userId = $this->session->get('user_id', 0);
            if (!is_int($userId) && !ctype_digit((string) $userId)) {
                $userId = 0;
            }

            if ((int) $userId > 0) {
                $userId = (int) $userId;

                // Cleanup expired sessions for this user.
                $this->account->cleanup_expired_sessions($userId);

                // Ensure current session is registered (logout all devices support)
                $sid = $this->session_id();
                $metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sid);
                $meta = $this->account->get_user_meta($userId, $metaKey);
                if ($meta !== null) {
                    // Check expiry within the stored payload
                    $payload = json_decode((string) $meta, true);
                    if (is_array($payload) && isset($payload['expires_at']) && is_numeric($payload['expires_at'])) {
                        if (time() > (int) $payload['expires_at']) {
                            // session expired
                            $this->unregister_session($userId, $sid);
                            return false;
                        }
                    }
                    return $this->account->get_user_by_id($userId) !== null;
                }
            }

            // If there is no session, try to auto-login using the remember cookie.
            $rememberToken = mol_cookie()->get('remember_me');
            if ($rememberToken) {
                $remember = $this->account->get_user_by_remember_token($rememberToken);
                if ($remember && !empty($remember['user'])) {
                    $user = $remember['user'];
                    $meta = $remember['meta'];

                    $expires = (int) ($meta['expires'] ?? 0);
                    if ($expires > 0 && time() > $expires) {
                        mol_cookie()->delete('remember_me');
                        return false;
                    }

                    // rotate token on auto-login
                    $newToken = bin2hex(random_bytes(32));
                    $newHash = password_hash($newToken, PASSWORD_DEFAULT);
                    $newExpires = time() + (60 * 60 * 24 * 30);

                    $metaData = json_encode([
                        'hash' => $newHash,
                        'expires' => $newExpires,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'created_at' => date('c'),
                    ], JSON_THROW_ON_ERROR);

                    $this->account->set_user_meta((int) $user['id'], 'remember_token', $metaData);
                    mol_cookie()->set('remember_me', $newToken, $newExpires, ['secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);

                    // restore session
                    $this->session->regenerate(true);
                    $this->session->set('user_id', (int) $user['id']);
                    $this->session->set('logged_in_at', time());
                    $this->register_session((int) $user['id']);
                    return true;
                }

                // invalid token: clear it
                mol_cookie()->delete('remember_me');
            }

            return false;
        }

        public function id(): ?int
        {
            return $this->session->get('user_id') ? (int) $this->session->get('user_id') : null;
        }

        public function verify_two_factor(string $code, ?string $recoveryCode = null): bool
        {
            $userId = (int) ($this->session->get('two_factor_user_id') ?? 0);
            if ($userId <= 0) {
                return false;
            }

            $valid = $this->account->verify_two_factor_code($userId, $code);
            if (!$valid && $recoveryCode) {
                $valid = $this->account->use_two_factor_recovery_code($userId, $recoveryCode);
                if ($valid) {
                    mol_audit($userId, $userId, '2fa_recovery_used');
                }
            }

            if (!$valid) {
                return false;
            }

            $remember = (bool) $this->session->get('two_factor_remember');
            $this->session->delete('two_factor_user_id');
            $this->session->delete('two_factor_remember');

            $this->completeLogin($userId, $remember);
            return true;
        }

        public function generate_two_factor_secret(int $userId): string
        {
            return $this->account->generate_two_factor_secret($userId);
        }

        public function confirm_two_factor_secret(int $userId, string $code): bool
        {
            return $this->account->confirm_two_factor_secret($userId, $code);
        }

        public function generate_two_factor_recovery_codes(int $userId, int $count = 10): array
        {
            return $this->account->generate_two_factor_recovery_codes($userId, $count);
        }

        public function clear_two_factor_recovery_codes(int $userId): bool
        {
            return $this->account->clear_two_factor_recovery_codes($userId);
        }

        public function disable_two_factor(int $userId): void
        {
            $this->account->disable_two_factor($userId);
        }

        public function current_user(): ?array
        {
            $id = $this->id();
            return $id ? $this->account->get_user_by_id($id) : null;
        }

        public function hash_password(string $password): string
        {
            $pepper = AUTHENTICATION['PEPPER']['VALUE'] ?? '';
            $options = ['cost' => AUTHENTICATION['COST']];
            return password_hash($password . $pepper, AUTHENTICATION['ALGORITHM'], $options);
        }

        public function verify_password(string $password, string $hash): bool
        {
            $pepper = AUTHENTICATION['PEPPER']['VALUE'] ?? '';
            return password_verify($password . $pepper, $hash);
        }

        public function register(array $data): bool
        {
            if (empty($data['user_login']) || empty($data['user_pass']) || empty($data['user_email'])) {
                return false;
            }

            $userData = [
                'user_login' => $data['user_login'],
                'user_email' => $data['user_email'],
                'user_pass' => $this->hash_password($data['user_pass']),
                'display_name' => $data['display_name'] ?? $data['user_login'],
                'user_registered' => date('Y-m-d H:i:s'),
            ];

            return $this->account->create_user($userData);
        }
    }
