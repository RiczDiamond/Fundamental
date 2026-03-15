<?php

    /**
     * Simple cookie wrapper for consistent cookie handling.
     *
     * Supports optional persistence to the database (via usermeta) for things like
     * "remember me" tokens.
     */
    class Cookie
    {
        private ?PDO $link;
        private array $defaults = [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        public function __construct($link = null, array $defaults = [])
        {
            $this->link = $link instanceof PDO ? $link : null;
            $this->defaults = array_merge($this->defaults, $defaults);
        }

        public function set(string $name, string $value, int $expires = 0, array $options = []): bool
        {
            $opts = array_merge($this->defaults, $options);
            $opts['expires'] = $expires;

            return setcookie($name, $value, $opts);
        }

        public function get(string $name, $default = null)
        {
            return $_COOKIE[$name] ?? $default;
        }

        public function has(string $name): bool
        {
            return array_key_exists($name, $_COOKIE);
        }

        public function delete(string $name, array $options = []): bool
        {
            $opts = array_merge($this->defaults, $options);
            $opts['expires'] = time() - 3600;

            return setcookie($name, '', $opts);
        }

        public function all(): array
        {
            return $_COOKIE;
        }

        /**
         * Persist a cookie value in the database (user meta) as well.
         *
         * @param int $userId
         */
        public function persist(int $userId, string $name, string $value, int $expires = 0): bool
        {
            $ok = $this->set($name, $value, $expires);
            if (!$ok || !$this->link) {
                return $ok;
            }

            $metaKey = 'cookie_' . $name;
            $metaValue = json_encode(['value' => $value, 'expires' => $expires], JSON_THROW_ON_ERROR);

            // Upsert in usermeta
            $existing = get_row('SELECT umeta_id FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key = :meta_key LIMIT 1', [
                'user_id' => $userId,
                'meta_key' => $metaKey,
            ]);

            if ($existing) {
                return update('usermeta', ['meta_value' => $metaValue], ['umeta_id' => $existing['umeta_id']]);
            }

            return insert('usermeta', ['user_id' => $userId, 'meta_key' => $metaKey, 'meta_value' => $metaValue]);
        }

        /**
         * Retrieve a persisted cookie value from the database for a given user.
         */
        public function retrievePersisted(int $userId, string $name, $default = null)
        {
            if (!$this->link) {
                return $default;
            }

            $metaKey = 'cookie_' . $name;
            $row = get_row('SELECT meta_value FROM ' . table('usermeta') . ' WHERE user_id = :user_id AND meta_key = :meta_key LIMIT 1', [
                'user_id' => $userId,
                'meta_key' => $metaKey,
            ]);

            if (!$row || empty($row['meta_value'])) {
                return $default;
            }

            $payload = json_decode((string) $row['meta_value'], true);
            if (!is_array($payload) || !isset($payload['value'])) {
                return $default;
            }

            return $payload['value'];
        }

        /**
         * Remove persistent cookie value from the database (and remove browser cookie).
         */
        public function clearPersisted(int $userId, string $name, array $options = []): bool
        {
            $ok = $this->delete($name, $options);
            if (!$this->link) {
                return $ok;
            }

            $metaKey = 'cookie_' . $name;
            delete('usermeta', ['user_id' => $userId, 'meta_key' => $metaKey]);
            return $ok;
        }
    }
