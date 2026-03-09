<?php

    class Account {

        private PDO $link;

        public function __construct(PDO $link) {
            $this->link = $link;
        }

        public function get_user_by_id(int $id): ?array {
            $stmt = $this->link->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();
            return is_array($user) ? $user : null;
        }

        public function get_user_by_login(string $login): ?array {
            $stmt = $this->link->prepare('SELECT * FROM users WHERE user_login = :login LIMIT 1');
            $stmt->execute(['login' => $login]);
            $user = $stmt->fetch();
            return is_array($user) ? $user : null;
        }

        public function get_user_by_email(string $email): ?array {
            $stmt = $this->link->prepare('SELECT * FROM users WHERE user_email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            return is_array($user) ? $user : null;
        }

        public function save_remember_token(string $selector, array $payload): void {
            $stmt = $this->link->prepare(
                'INSERT INTO options (option_name, option_value, autoload)
                VALUES (:name, :value, :autoload)
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)'
            );
            $stmt->execute([
                'name' => 'auth_remember_' . $selector,
                'value' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'autoload' => 'no',
            ]);
        }

        public function get_remember_token(string $selector): ?array {
            $stmt = $this->link->prepare('SELECT option_value FROM options WHERE option_name = :name LIMIT 1');
            $stmt->execute(['name' => 'auth_remember_' . $selector]);
            $value = $stmt->fetchColumn();
            $decoded = $value ? json_decode($value, true) : null;
            return is_array($decoded) ? $decoded : null;
        }

        public function delete_remember_token(string $selector): void {
            $stmt = $this->link->prepare('DELETE FROM options WHERE option_name = :name LIMIT 1');
            $stmt->execute(['name' => 'auth_remember_' . $selector]);
        }

        public function save_reset_token(string $selector, array $payload): void {
            $stmt = $this->link->prepare(
                'INSERT INTO options (option_name, option_value, autoload)
                    VALUES (:name, :value, :autoload)
                    ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)'
            );
            $stmt->execute([
                'name' => 'password_reset_' . $selector,
                'value' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'autoload' => 'no',
            ]);
        }

        public function get_reset_token(string $selector): ?array {
            $stmt = $this->link->prepare('SELECT option_value FROM options WHERE option_name = :name LIMIT 1');
            $stmt->execute(['name' => 'password_reset_' . $selector]);
            $value = $stmt->fetchColumn();
            $decoded = $value ? json_decode($value, true) : null;
            return is_array($decoded) ? $decoded : null;
        }

        public function delete_reset_token(string $selector): void {
            $stmt = $this->link->prepare('DELETE FROM options WHERE option_name = :name LIMIT 1');
            $stmt->execute(['name' => 'password_reset_' . $selector]);
        }

        public function update_user_password(int $userId, string $hashedPassword): void {
            $stmt = $this->link->prepare('UPDATE users SET user_pass = :pass WHERE id = :id LIMIT 1');
            $stmt->execute(['pass' => $hashedPassword, 'id' => $userId]);
        }

        /* Login failures en andere options helpers kunnen hier ook */

    }