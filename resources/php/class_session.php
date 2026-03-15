<?php

    class Session
    {
        private $link;
        private $started = false;

        public function __construct($link = null)
        {
            $this->link = $link;
        }

        /**
         * Ensure the PHP session is started.
         */
        public function start(): void
        {
            if ($this->started) {
                return;
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $this->started = true;
        }

        public function isActive(): bool
        {
            return $this->started || session_status() === PHP_SESSION_ACTIVE;
        }

        public function get(string $key, $default = null)
        {
            $this->start();
            return $_SESSION[$key] ?? $default;
        }

        public function pull(string $key, $default = null)
        {
            $this->start();
            $value = $_SESSION[$key] ?? $default;
            unset($_SESSION[$key]);
            return $value;
        }

        public function set(string $key, $value): void
        {
            $this->start();
            $_SESSION[$key] = $value;
        }

        public function flash(string $key, $value): void
        {
            $this->start();
            $_SESSION['_flash'][$key] = $value;
        }

        public function flashGet(string $key, $default = null)
        {
            $this->start();
            $value = $_SESSION['_flash'][$key] ?? $default;
            unset($_SESSION['_flash'][$key]);
            return $value;
        }

        public function has(string $key): bool
        {
            $this->start();
            return array_key_exists($key, $_SESSION);
        }

        public function delete(string $key): void
        {
            $this->start();
            unset($_SESSION[$key]);
        }

        public function id(): ?string
        {
            $this->start();
            return session_id();
        }

        public function regenerate(bool $deleteOldSession = true): void
        {
            $this->start();
            session_regenerate_id($deleteOldSession);
        }

        public function destroy(): void
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            session_destroy();
            $this->started = false;
        }
    }
