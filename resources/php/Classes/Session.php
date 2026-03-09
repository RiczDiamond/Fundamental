<?php

    class Session {

        public function start(): void {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
        }

        public function destroy(): void {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        }

        public function set(string $key, mixed $value): void {
            $_SESSION[$key] = $value;
        }

        public function get(string $key): mixed {
            return $_SESSION[$key] ?? null;
        }

        public function has(string $key): bool {
            return isset($_SESSION[$key]);
        }

        public function regenerate(): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        }
        
    }