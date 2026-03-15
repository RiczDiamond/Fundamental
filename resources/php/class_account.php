<?php

    declare(strict_types=1);

    class Account
    {
        private $link;
        private string $table;

        public function __construct($link)
        {
            $this->link = $link;
            $this->table = table('users');
        }

        public function get_user_by_id(int $id): ?array
        {
            return get_row('SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1', ['id' => $id]);
        }

        public function get_user_by_login(string $login): ?array
        {
            return get_row('SELECT * FROM ' . $this->table . ' WHERE user_login = :login LIMIT 1', ['login' => $login]);
        }

        public function get_user_by_email(string $email): ?array
        {
            return get_row('SELECT * FROM ' . $this->table . ' WHERE user_email = :email LIMIT 1', ['email' => $email]);
        }

        public function update_user_password(int $userId, string $hash): bool
        {
            return update('users', ['user_pass' => $hash], ['id' => $userId]);
        }

        public function save_reset_token(string $selector, array $payload): bool
        {
            $metaKey = 'password_reset_' . $selector;
            $userId = (int) ($payload['user_id'] ?? 0);

            // Limit to one active reset token per user to avoid buildup and reduce risk.
            // This also prevents allowing multiple tokens simultaneously for the same account.
            if ($userId > 0) {
                db_query(
                    'DELETE FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key LIKE :like',
                    ['user_id' => $userId, 'like' => 'password_reset_%']
                );
            }

            // Remove any existing token for this selector (in case it exists).
            delete('usermeta', ['meta_key' => $metaKey]);

            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            return insert('usermeta', [
                'user_id' => $userId,
                'meta_key' => $metaKey,
                'meta_value' => $payloadJson,
            ]);
        }

        public function get_reset_token(string $selector): ?array
        {
            $metaKey = 'password_reset_' . $selector;
            $row = get_row('SELECT * FROM ' . table('usermeta') . ' WHERE meta_key = :meta_key LIMIT 1', ['meta_key' => $metaKey]);

            if (!$row) {
                return null;
            }

            $payload = json_decode((string) ($row['meta_value'] ?? ''), true);
            if (!is_array($payload)) {
                return null;
            }

            return $payload;
        }

        public function delete_reset_token(string $selector): bool
        {
            $metaKey = 'password_reset_' . $selector;
            return delete('usermeta', ['meta_key' => $metaKey]);
        }

        public function get_all(): array
        {
            return get_results('SELECT * FROM ' . $this->table . ' ORDER BY user_registered DESC');
        }

        public function create_user(array $data): bool
        {
            return insert('users', $data);
        }

        public function update_user(int $id, array $data): bool
        {
            return update('users', $data, ['id' => $id]);
        }

        public function set_user_status(int $id, int $status): bool
        {
            return update('users', ['user_status' => $status], ['id' => $id]);
        }
    }
