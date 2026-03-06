<?php

    class Cookie {

        private $secure;
        private $httponly = true;
        private $samesite = 'Strict';
        private $path = '/';

        public function __construct()
        {
            $this->secure = isset($_SERVER['HTTPS']);
        }

        public function set($name, $value, $expire = 0)
        {
            setcookie(
                $name,
                $value,
                [
                    'expires' => $expire,
                    'path' => $this->path,
                    'secure' => $this->secure,
                    'httponly' => $this->httponly,
                    'samesite' => $this->samesite
                ]
            );

            $_COOKIE[$name] = $value;
        }

        public function get($name)
        {
            return $_COOKIE[$name] ?? null;
        }

        public function has($name)
        {
            return isset($_COOKIE[$name]);
        }

        public function delete($name)
        {
            setcookie(
                $name,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => $this->path,
                    'secure' => $this->secure,
                    'httponly' => $this->httponly,
                    'samesite' => $this->samesite
                ]
            );

            unset($_COOKIE[$name]);
        }
    }