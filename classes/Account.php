<?php

    class Account {

        private $link;

        public $id;
        public $email;
        public $username;
        public $password;
        public $first_name;
        public $last_name;
        public $display_name;
        public $profile_picture;
        public $gender;
        public $birth_date;
        public $role;
        public $status;
        public $deletion_requested_at;
        public $banned_until;
        public $ban_reason;
        public $email_verified;
        public $last_login;
        public $last_ip;
        public $banned;
        public $trash;
        public $street;
        public $street_num;
        public $postal;
        public $city;
        public $country;
        public $comment;
        public $bio;
        public $created_at;
        public $updated_at;
        public $last_edit_by;

        public $auth = 'auth';

        private $table = 'users';

        public function __construct($link) {

            $this->link = $link;
        
        }

        public function set($data) {

                if (empty($data) || !is_array($data)) {
                    // reset
                    foreach (get_object_vars($this) as $k => $v) {
                        if (in_array($k, ['link', 'auth', 'table'])) continue;
                        $this->$k = null;
                    }
                    return true;
                }

                foreach ($data as $k => $v) {
                    if (property_exists($this, $k)) {
                        $this->$k = $v;
                    }
                }

                return true;

        }

        public function get($id) {

                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return null;
                // populate object
                foreach ($row as $k => $v) {
                    if (property_exists($this, $k)) {
                        $this->$k = $v;
                    }
                }
                return $row;

        }

        public function check() {

                if (!empty($this->id)) {
                    $stmt = $this->link->prepare("SELECT 1 FROM {$this->table} WHERE id = ? LIMIT 1");
                    $stmt->execute([$this->id]);
                    return (bool) $stmt->fetchColumn();
                }

                if (!empty($this->username) || !empty($this->email)) {
                    $stmt = $this->link->prepare("SELECT 1 FROM {$this->table} WHERE username = ? OR email = ? LIMIT 1");
                    $stmt->execute([$this->username, $this->email]);
                    return (bool) $stmt->fetchColumn();
                }

                return false;

        }

        public function put($data) {

                if (empty($data) || !is_array($data)) return false;

                $id = $data['id'] ?? $this->id ?? null;
                if (empty($id)) return false;

                if (array_key_exists('trash', $data) && !array_key_exists('deletion_requested_at', $data)) {
                    $data['deletion_requested_at'] = ((int)$data['trash'] === 1) ? date('Y-m-d H:i:s') : null;
                }
                if (array_key_exists('banned', $data) && !array_key_exists('status', $data)) {
                    $data['status'] = ((int)$data['banned'] === 1) ? 'banned' : 'active';
                    if ((int)$data['banned'] === 0) {
                        $data['banned_until'] = null;
                        $data['ban_reason'] = null;
                    }
                }

                $allowed = [
                    'username','email','password','gender','first_name','last_name','display_name','birth_date',
                    'role','status','deletion_requested_at','banned_until','ban_reason','email_verified','last_login','last_ip'
                ];

                $sets = [];
                $params = [];

                foreach ($allowed as $col) {
                    if (array_key_exists($col, $data)) {
                        if ($col === 'password') {
                            $sets[] = "password = ?";
                            $params[] = password_hash($data[$col], PASSWORD_DEFAULT);
                        } else {
                            $sets[] = "{$col} = ?";
                            $params[] = $data[$col];
                        }
                    }
                }

                if (empty($sets)) return false;

                $sets[] = 'updated_at = NOW()';
                $params[] = $id;
                $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?";
                $stmt = $this->link->prepare($sql);
                return $stmt->execute($params);

        }

        public function trash($id) {

            // Soft-delete: mark deletion request timestamp
            $stmt = $this->link->prepare("UPDATE {$this->table} SET deletion_requested_at = NOW(), updated_at = NOW() WHERE id = ?");
                return $stmt->execute([$id]);

        }

        private function copy($id) {

                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return null;

                unset($row['id']);
                // adjust username/email to avoid uniques
                if (!empty($row['username'])) {
                    $row['username'] = $row['username'] . '_copy_' . time();
                }
                if (!empty($row['email'])) {
                    $row['email'] = null;
                }

                $cols = array_keys($row);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES ({$placeholders})";
                $stmt = $this->link->prepare($sql);
                $stmt->execute(array_values($row));
                return $this->link->lastInsertId();

        }

        public function delete($id) {

                $stmt = $this->link->prepare("DELETE FROM {$this->table} WHERE id = ?");
                return $stmt->execute([$id]);

        }

        private function get_trash_status($id) {

            $stmt = $this->link->prepare("SELECT deletion_requested_at FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
            return $v ? !empty($v['deletion_requested_at']) : null;

        }

        public function restore($id) {

            $stmt = $this->link->prepare("UPDATE {$this->table} SET deletion_requested_at = NULL, updated_at = NOW() WHERE id = ?");
                return $stmt->execute([$id]);

        }

        public function find($search) {

                $q = '%' . strtr($search, ['%' => '\%', '_' => '\_', '\\' => '\\\\']) . '%';
                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE username LIKE ? OR email LIKE ? LIMIT 50");
                $stmt->execute([$q, $q]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function get_all($wildcard = false) {

                if ($wildcard && is_string($wildcard)) {
                    return $this->find($wildcard);
                }

                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE deletion_requested_at IS NULL ORDER BY created_at DESC");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function get_trash($limit = 200) {

                $limit = max(1, min((int)$limit, 500));
                $stmt = $this->link->prepare(
                    "SELECT id, username, email, role, status, deletion_requested_at, banned_until, ban_reason, updated_at
                     FROM {$this->table}
                     WHERE deletion_requested_at IS NOT NULL
                     ORDER BY updated_at DESC
                     LIMIT {$limit}"
                );
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function get_dashboard_stats() {

                $stats = [];

                $stats['users_total'] = (int)$this->link->query(
                    "SELECT COUNT(*) FROM {$this->table} WHERE deletion_requested_at IS NULL"
                )->fetchColumn();

                $stats['users_active'] = (int)$this->link->query(
                    "SELECT COUNT(*) FROM {$this->table} WHERE deletion_requested_at IS NULL AND status='active'"
                )->fetchColumn();

                $stats['users_banned'] = (int)$this->link->query(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status='banned' OR (banned_until IS NOT NULL AND banned_until > NOW())"
                )->fetchColumn();

                $stats['users_trash'] = (int)$this->link->query(
                    "SELECT COUNT(*) FROM {$this->table} WHERE deletion_requested_at IS NOT NULL"
                )->fetchColumn();

                $stats['users_active_week'] = (int)$this->link->query(
                    "SELECT COUNT(*) FROM {$this->table} WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                )->fetchColumn();

                return $stats;

        }

        public function get_groups($id) {

            $stmt = $this->link->prepare(
                "SELECT g.group_id, g.group_name, g.description
                 FROM perm_account_group ag
                 JOIN perm_group g ON ag.group_id = g.group_id
                 WHERE ag.account_id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function get_permissions($id) {

            // Direct permissions
            $stmt = $this->link->prepare(
                "SELECT p.permission_name
                 FROM perm_account_permission ap
                 JOIN perm_permission p ON ap.permission_id = p.permission_id
                 WHERE ap.account_id = ?"
            );
            $stmt->execute([$id]);
            $direct = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Group permissions
            $stmt = $this->link->prepare(
                "SELECT DISTINCT p.permission_name
                 FROM perm_account_group ag
                 JOIN perm_group_permission gp ON ag.group_id = gp.group_id
                 JOIN perm_permission p ON gp.permission_id = p.permission_id
                 WHERE ag.account_id = ?"
            );
            $stmt->execute([$id]);
            $groupPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $all = array_values(array_unique(array_merge($direct ?: [], $groupPerms ?: [])));
            return $all;

        }

        public function ban($id, $reason = null, $time = null) {

            $params = ['banned'];
            $sql = "UPDATE {$this->table} SET status = ?, updated_at = NOW()";

                if (!empty($time)) {
                    $sql .= ", banned_until = ?";
                    $params[] = date('Y-m-d H:i:s', $time);
                }

                if (!empty($reason)) {
                    $sql .= ", ban_reason = ?";
                    $params[] = $reason;
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;
                $stmt = $this->link->prepare($sql);
                return $stmt->execute($params);

        }

        public function unban($id) {

            $stmt = $this->link->prepare("UPDATE {$this->table} SET status = 'active', banned_until = NULL, ban_reason = NULL, updated_at = NOW() WHERE id = ?");
                return $stmt->execute([$id]);

        }

        private function get_ban_value($id) {

                $stmt = $this->link->prepare("SELECT status, banned_until, ban_reason FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($v) {
                    $v['banned'] = ($v['status'] === 'banned') || (!empty($v['banned_until']) && strtotime($v['banned_until']) > time());
                }
                return $v ?: null;

        }

    }