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

    /**
     * Find a user & remember-token row matching the provided token.
     *
     * Returns null if none found, otherwise returns ['user' => ..., 'meta' => ...].
     */
    public function get_user_by_remember_token(string $token): ?array
    {
        // Store only a hash in usermeta, so we compare using password_verify.
        $rows = get_results('SELECT user_id, meta_value FROM ' . table('usermeta') . ' WHERE meta_key = :meta_key', ['meta_key' => 'remember_token']);
        foreach ($rows as $row) {
            if (!isset($row['user_id'], $row['meta_value'])) {
                continue;
            }

            $payload = json_decode((string) $row['meta_value'], true);
            if (!is_array($payload) || empty($payload['hash'])) {
                continue;
            }

            if (!password_verify($token, $payload['hash'])) {
                continue;
            }

            $user = $this->get_user_by_id((int) $row['user_id']);
            if (!$user) {
                continue;
            }

            return ['user' => $user, 'meta' => $payload];
        }

        return null;
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

            $payload['expires'] = time() + (60 * 60); // 1 hour expiry

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
                    'expires_at' => $payload['expires_at'] ?? null,
                    'ip' => $payload['ip'] ?? null,
                    'user_agent' => $payload['user_agent'] ?? null,
                ];
            }

            return $sessions;
        }

        public function is_two_factor_enabled(int $userId): bool
        {
            // 2FA support removed; always treat it as disabled.
            return false;
        }

        public function is_two_factor_pending(int $userId): bool
        {
            return (bool) $this->get_user_meta($userId, 'two_factor_secret_pending');
        }

        public function generate_two_factor_secret(int $userId): string
        {
            $secret = self::totp_generate_secret();
            $this->set_user_meta($userId, 'two_factor_secret_pending', $secret);
            return $secret;
        }

        public function confirm_two_factor_secret(int $userId, string $code): bool
        {
            $secret = $this->get_user_meta($userId, 'two_factor_secret_pending');
            if (!$secret) {
                return false;
            }

            if (!self::totp_verify_code($secret, $code)) {
                return false;
            }

            // Move pending secret to active secret
            $this->set_user_meta($userId, 'two_factor_secret', $secret);
            $this->set_user_meta($userId, 'two_factor_enabled', '1');
            $this->delete_user_meta($userId, 'two_factor_secret_pending');
            return true;
        }

        public function disable_two_factor(int $userId): bool
        {
            $this->delete_user_meta($userId, 'two_factor_secret');
            $this->delete_user_meta($userId, 'two_factor_secret_pending');
            $this->delete_user_meta($userId, 'two_factor_recovery_codes');
            return $this->delete_user_meta($userId, 'two_factor_enabled');
        }

        public function generate_two_factor_recovery_codes(int $userId, int $count = 10): array
        {
            $codes = [];
            $hashes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = bin2hex(random_bytes(4));
                $codes[] = $code;
                $hashes[] = password_hash($code, PASSWORD_DEFAULT);
            }

            $this->set_user_meta($userId, 'two_factor_recovery_codes', json_encode($hashes, JSON_THROW_ON_ERROR));
            return $codes;
        }

        public function get_two_factor_recovery_hashes(int $userId): array
        {
            $raw = $this->get_user_meta($userId, 'two_factor_recovery_codes');
            if (!$raw) {
                return [];
            }

            $arr = json_decode((string) $raw, true);
            if (!is_array($arr)) {
                return [];
            }

            return $arr;
        }

        public function use_two_factor_recovery_code(int $userId, string $code): bool
        {
            $hashes = $this->get_two_factor_recovery_hashes($userId);
            if (!$hashes) {
                return false;
            }

            foreach ($hashes as $idx => $hash) {
                if (password_verify($code, $hash)) {
                    unset($hashes[$idx]);
                    $hashes = array_values($hashes);
                    if (empty($hashes)) {
                        $this->delete_user_meta($userId, 'two_factor_recovery_codes');
                    } else {
                        $this->set_user_meta($userId, 'two_factor_recovery_codes', json_encode($hashes, JSON_THROW_ON_ERROR));
                    }
                    return true;
                }
            }

            return false;
        }

        public function clear_two_factor_recovery_codes(int $userId): bool
        {
            return $this->delete_user_meta($userId, 'two_factor_recovery_codes');
        }

        public function verify_two_factor_code(int $userId, string $code): bool
        {
            $secret = $this->get_two_factor_secret($userId);
            if (!$secret) {
                return false;
            }

            return self::totp_verify_code($secret, $code);
        }

        /**
         * TOTP helpers (no external dependencies).
         */
        private static function totp_generate_secret(int $length = 16): string
        {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            $secret = '';
            for ($i = 0; $i < $length; $i++) {
                $secret .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $secret;
        }

        private static function totp_get_code(string $secret, int $timeSlice = null): string
        {
            $timeSlice = $timeSlice ?: floor(time() / 30);
            $secretKey = self::totp_base32_decode($secret);
            $time = pack('N*', 0) . pack('N*', $timeSlice);
            $hash = hash_hmac('sha1', $time, $secretKey, true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $truncatedHash = substr($hash, $offset, 4);
            $code = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;
            return str_pad((string) ($code % 1000000), 6, '0', STR_PAD_LEFT);
        }

        private static function totp_verify_code(string $secret, string $code, int $discrepancy = 0): bool
        {
            // Allow some clock drift by checking +/- a few 30-second windows.
            // Default is read from env (TOTP_DRIFT) or 2 if unset.
            $discrepancy = $discrepancy ?: cfg_env_int('TOTP_DRIFT', 2);

            $code = trim($code);
            if ($code === '') {
                return false;
            }

            for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
                $calc = self::totp_get_code($secret, floor(time() / 30) + $i);
                if (hash_equals($calc, $code)) {
                    return true;
                }
            }

            return false;
        }

        public static function totp_codes(string $secret, int $discrepancy = null): array
        {
            $discrepancy = $discrepancy ?? cfg_env_int('TOTP_DRIFT', 2);
            $codes = [];
            for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
                $codes[] = self::totp_get_code($secret, floor(time() / 30) + $i);
            }
            return $codes;
        }

        private static function totp_base32_decode(string $secret): string
        {
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            $clean = preg_replace('/[^A-Z2-7]/', '', strtoupper($secret));
            $binary = '';

            foreach (str_split($clean) as $char) {
                $pos = strpos($alphabet, $char);
                if ($pos === false) {
                    continue;
                }
                $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }

            $bytes = '';
            foreach (str_split($binary, 8) as $byte) {
                if (strlen($byte) === 8) {
                    $bytes .= chr(bindec($byte));
                }
            }

            return $bytes;
        }

        public function cleanup_expired_sessions(int $userId): int
        {
            $sessions = $this->get_user_sessions($userId);
            $now = time();
            $removed = 0;

            foreach ($sessions as $session) {
                $expiresAt = isset($session['expires_at']) ? strtotime((string) $session['expires_at']) : null;
                if ($expiresAt && $expiresAt < $now) {
                    $this->delete_user_meta($userId, 'session_' . $session['id']);
                    $removed++;
                }
            }

            return $removed;
        }

        /**
         * Cleanup expired sessions for all users.
         *
         * This is intended to be run via cron/job to avoid unbounded growth.
         */
        public function cleanup_all_expired_sessions(): int
        {
            $rows = get_results('SELECT DISTINCT user_id FROM ' . table('usermeta') . " WHERE meta_key LIKE :like", ['like' => 'session_%']);
            $totalRemoved = 0;
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                $totalRemoved += $this->cleanup_expired_sessions($userId);
            }
            return $totalRemoved;
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
