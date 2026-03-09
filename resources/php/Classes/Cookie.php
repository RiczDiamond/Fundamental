<?php

    class Cookie {

        public function set(string $name, string $value, int $expires): void {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $options = [
                'expires' => $expires,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            // Only include domain option when explicitly provided/non-empty
            $domain = '';
            if (!empty($domain)) {
                $options['domain'] = $domain;
            }

            setcookie($name, $value, $options);
            $_COOKIE[$name] = $value;
        }

        public function delete(string $name): void {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $options = [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            $domain = '';
            if (!empty($domain)) {
                $options['domain'] = $domain;
            }

            setcookie($name, '', $options);
            unset($_COOKIE[$name]);
        }

        public function get(string $name): ?string {
            return $_COOKIE[$name] ?? null;
        }

    }