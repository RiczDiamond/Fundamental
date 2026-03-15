<?php

    declare(strict_types=1);

    class Auth
    {
        private $link;
        private Account $account;

        public function __construct($link)
        {
            $this->link = $link;
            $this->account = new Account($link);
        }

        public function login(string $login, string $password, bool $remember = false): bool
        {
            if ($login === '' || $password === '') {
                return false;
            }

            $user = filter_var($login, FILTER_VALIDATE_EMAIL)
                ? $this->account->get_user_by_email($login)
                : $this->account->get_user_by_login($login);

            if (!$user) {
                return false;
            }

            $hash = $user['user_pass'] ?? '';
            if (!$this->verify_password($password, $hash)) {
                return false;
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
            $_SESSION['logged_in_at'] = time();

            // Track active session for logout-on-all-devices.
            $this->register_session((int) ($user['id'] ?? 0));

            // Track last login for audit/user overview.
            $this->account->set_last_login((int) ($user['id'] ?? 0), date('Y-m-d H:i:s'));

            if ($remember) {
                $lifetime = time() + (60 * 60 * 24 * 30);
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie(session_name(), session_id(), $lifetime, '/', '', $secure, true);
            }

            return true;
        }

        private function session_id(): string
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            return session_id();
        }

        private function register_session(int $userId): void
        {
            $sid = $this->session_id();
            if (!$sid) {
                return;
            }

            $metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sid);
            $metaValue = json_encode([
                'created_at' => date('c'),
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
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $this->unregister_session((int) $userId, $this->session_id());
            }

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            session_destroy();
        }

        public function check(): bool
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $userId = $_SESSION['user_id'] ?? 0;
            if (!is_int($userId) && !ctype_digit((string) $userId)) {
                return false;
            }

            $userId = (int) $userId;
            if ($userId <= 0) {
                return false;
            }

            // Ensure current session is registered (logout all devices support)
            $sid = $this->session_id();
            $metaKey = 'session_' . preg_replace('/[^a-z0-9]/i', '', $sid);
            $meta = $this->account->get_user_meta($userId, $metaKey);
            if ($meta === null) {
                return false;
            }

            return $this->account->get_user_by_id($userId) !== null;
        }

        public function id(): ?int
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $userId = $_SESSION['user_id'] ?? null;
            if ($userId === null) {
                return null;
            }

            return (int) $userId;
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
