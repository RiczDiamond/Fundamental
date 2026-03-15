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

        /**
         * Get a paginated list of users, optionally filtering by search, role, and status.
         *
         * @param array $args {
         *     @type string|null $search   Search term (login, email, display name)
         *     @type string|null $role     User role filter
         *     @type int|null    $status   User status filter (0=active,1=banned,2=deleted)
         *     @type int         $page     Page number (1-based)
         *     @type int         $per_page Results per page
         * }
         *
         * @return array
         */
        public function get_users(array $args = []): array
        {
            $where = [];
            $params = [];

            if (!empty($args['search'])) {
                $where[] = '(user_login LIKE :search_login OR user_email LIKE :search_email OR display_name LIKE :search_display)';
                $params['search_login'] = '%' . $args['search'] . '%';
                $params['search_email'] = '%' . $args['search'] . '%';
                $params['search_display'] = '%' . $args['search'] . '%';
            }

            if (!empty($args['role'])) {
                $where[] = 'user_role = :role';
                $params['role'] = $args['role'];
            }

            if (isset($args['status']) && $args['status'] !== '') {
                $where[] = 'user_status = :status';
                $params['status'] = (int) $args['status'];
            }

            $whereSql = '';
            if (!empty($where)) {
                $whereSql = 'WHERE ' . implode(' AND ', $where);
            }

            $page = (int) ($args['page'] ?? 1);
            if ($page < 1) {
                $page = 1;
            }
            $perPage = (int) ($args['per_page'] ?? 25);
            if ($perPage < 1) {
                $perPage = 25;
            }
            if ($perPage > 100) {
                $perPage = 100;
            }

            $offset = ($page - 1) * $perPage;

            // MySQL doesn't allow binding LIMIT/OFFSET parameters in some PDO configurations,
            // so we inject the numeric values directly after casting.
            $limit = (int) $perPage;
            $offset = (int) $offset;
            $sql = 'SELECT * FROM ' . $this->table . ' ' . $whereSql . ' ORDER BY user_registered DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

            return get_results($sql, $params);
        }

        public function get_users_count(array $args = []): int
        {
            $where = [];
            $params = [];

            if (!empty($args['search'])) {
                $where[] = '(user_login LIKE :search_login OR user_email LIKE :search_email OR display_name LIKE :search_display)';
                $params['search_login'] = '%' . $args['search'] . '%';
                $params['search_email'] = '%' . $args['search'] . '%';
                $params['search_display'] = '%' . $args['search'] . '%';
            }

            if (!empty($args['role'])) {
                $where[] = 'user_role = :role';
                $params['role'] = $args['role'];
            }

            if (isset($args['status']) && $args['status'] !== '') {
                $where[] = 'user_status = :status';
                $params['status'] = (int) $args['status'];
            }

            $whereSql = '';
            if (!empty($where)) {
                $whereSql = 'WHERE ' . implode(' AND ', $where);
            }

            $sql = 'SELECT COUNT(*) FROM ' . $this->table . ' ' . $whereSql;
            return (int) get_var($sql, $params);
        }

        public function create_user(array $data): bool
        {
            $data['user_role'] = $data['user_role'] ?? 'user';
            return insert('users', $data);
        }

        public function set_last_login(int $id, string $dateTime): bool
        {
            return update('users', ['last_login' => $dateTime], ['id' => $id]);
        }

        public function set_updated_at(int $id, string $dateTime): bool
        {
            return update('users', ['updated_at' => $dateTime], ['id' => $id]);
        }

        public function update_user(int $id, array $data): bool
        {
            return update('users', $data, ['id' => $id]);
        }

        public function set_user_status(int $id, int $status): bool
        {
            return update('users', ['user_status' => $status], ['id' => $id]);
        }

        public function get_user_meta(int $userId, string $metaKey): ?string
        {
            $row = get_row('SELECT meta_value FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key = :meta_key LIMIT 1', [
                'user_id' => $userId,
                'meta_key' => $metaKey,
            ]);

            return $row['meta_value'] ?? null;
        }

        public function set_user_meta(int $userId, string $metaKey, string $metaValue): bool
        {
            // Update if exists, otherwise insert.
            $existing = $this->get_user_meta($userId, $metaKey);
            if ($existing !== null) {
                return update('usermeta', ['meta_value' => $metaValue], ['user_id' => $userId, 'meta_key' => $metaKey]);
            }
            return insert('usermeta', ['user_id' => $userId, 'meta_key' => $metaKey, 'meta_value' => $metaValue]);
        }

        public function delete_user_meta(int $userId, string $metaKey): bool
        {
            return delete('usermeta', ['user_id' => $userId, 'meta_key' => $metaKey]);
        }

        public function get_user_sessions(int $userId): array
        {
            $rows = get_results(
                'SELECT meta_key, meta_value FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key LIKE :like',
                ['user_id' => $userId, 'like' => 'session_%']
            );

            $sessions = [];
            foreach ($rows as $row) {
                $key = $row['meta_key'] ?? '';
                $sid = preg_replace('/^session_/', '', $key);

                $payload = json_decode((string) ($row['meta_value'] ?? ''), true);
                if (!is_array($payload)) {
                    continue;
                }

                $sessions[] = [
                    'id' => $sid,
                    'created_at' => $payload['created_at'] ?? null,
                    'ip' => $payload['ip'] ?? null,
                    'user_agent' => $payload['user_agent'] ?? null,
                ];
            }

            return $sessions;
        }

        public function delete_user_meta_like(int $userId, string $like): bool
        {
            // Delete all metas for the user where meta_key LIKE :like
            return db_query(
                'DELETE FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key LIKE :like',
                ['user_id' => $userId, 'like' => $like]
            );
        }

        public function get_pending_email_change(string $selector): ?array
        {
            $metaKey = 'pending_email_change_' . preg_replace('/[^a-z0-9]/i', '', $selector);
            $row = get_row('SELECT meta_value FROM ' . table('usermeta') . ' WHERE meta_key = :meta_key LIMIT 1', ['meta_key' => $metaKey]);
            if (!$row) {
                return null;
            }
            $payload = json_decode((string) ($row['meta_value'] ?? ''), true);
            if (!is_array($payload)) {
                return null;
            }
            return $payload;
        }

        public function apply_pending_email_change(string $selector, string $token): bool
        {
            $metaKey = 'pending_email_change_' . preg_replace('/[^a-z0-9]/i', '', $selector);
            $row = get_row('SELECT * FROM ' . table('usermeta') . ' WHERE meta_key = :meta_key LIMIT 1', ['meta_key' => $metaKey]);
            if (!$row) {
                return false;
            }
            $payload = json_decode((string) ($row['meta_value'] ?? ''), true);
            if (!is_array($payload)) {
                return false;
            }

            $userId = (int) ($payload['user_id'] ?? 0);
            $newEmail = trim((string) ($payload['new_email'] ?? ''));
            $tokenHash = $payload['token_hash'] ?? '';
            $expires = (int) ($payload['expires'] ?? 0);

            if ($userId <= 0 || $newEmail === '' || $tokenHash === '' || $expires <= time()) {
                return false;
            }

            if (!password_verify($token, $tokenHash)) {
                return false;
            }

            $ok = update('users', ['user_email' => $newEmail], ['id' => $userId]);
            if ($ok) {
                delete('usermeta', ['meta_key' => $metaKey]);
            }
            return $ok;
        }
    }
