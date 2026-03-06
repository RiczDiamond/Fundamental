<?php

    class Session {

        private $link;
        private $timeout = 3600; // 1 uur

        public $auth = 'auth';
        public $id;

        public function __construct($link)
        {
            $this->link = $link;

            if (session_status() === PHP_SESSION_NONE) {

                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                session_start();
            }

            $this->id = session_id();

            $this->checkTimeout();
        }

        private function checkTimeout()
        {
            if (isset($_SESSION['_last_activity'])) {

                if (time() - $_SESSION['_last_activity'] > $this->timeout) {

                    $this->destroy();
                }
            }

            $_SESSION['_last_activity'] = time();
        }

        public function set($key, $value)
        {
            $_SESSION[$key] = $value;
        }

        public function get($key)
        {
            return $_SESSION[$key] ?? null;
        }

        public function has($key)
        {
            return isset($_SESSION[$key]);
        }

        public function delete($key)
        {
            unset($_SESSION[$key]);
        }

        public function regenerate()
        {
            session_regenerate_id(true);
            $this->id = session_id();
        }

        public function destroy()
        {
            $_SESSION = [];

            if (ini_get("session.use_cookies")) {

                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 3600,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();
        }

        public function auth($userId)
        {
            $this->regenerate();
            $this->set('user_id', $userId);
        }

        public function user()
        {
            return $this->get('user_id');
        }

        public function check()
        {
            return $this->has('user_id');
        }

    }