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

                $allowed = [
                    'username','email','password','profile_picture','trash','street','street_num','postal','city','country',
                    'gender','comment','bio','last_edit','last_edit_by','first_name','last_name','display_name','birth_date',
                    'role','status','deletion_requested_at','banned_until','ban_reason','email_verified','last_login','last_ip','banned'
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

                $params[] = $id;
                $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?";
                $stmt = $this->link->prepare($sql);
                return $stmt->execute($params);

        }

        public function retrieve($id) {

                // Soft-delete: mark as trash
                $stmt = $this->link->prepare("UPDATE {$this->table} SET trash = 1, last_edit = NOW() WHERE id = ?");
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

                $stmt = $this->link->prepare("SELECT trash FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
                return $v ? (bool) $v['trash'] : null;

        }

        public function restore($id) {

                $stmt = $this->link->prepare("UPDATE {$this->table} SET trash = 0, last_edit = NOW() WHERE id = ?");
                return $stmt->execute([$id]);

        }

        public function find($search) {

                $q = "%" . str_replace('%', '\\%', $search) . "%";
                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE username LIKE ? OR email LIKE ? LIMIT 50");
                $stmt->execute([$q, $q]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function get_all($wildcard = false) {

                if ($wildcard && is_string($wildcard)) {
                    return $this->find($wildcard);
                }

                $stmt = $this->link->prepare("SELECT * FROM {$this->table} WHERE COALESCE(trash,0) = 0 ORDER BY created_at DESC");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

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

                $params = [1, $id];
                $sql = "UPDATE {$this->table} SET banned = ?, last_edit = NOW()";
                // if banned_until column exists and $time provided
                $hasUntil = false;
                $check = $this->link->query("SHOW COLUMNS FROM {$this->table} LIKE 'banned_until'");
                if ($check && $check->rowCount() > 0 && !empty($time)) {
                    $sql .= ", banned_until = ?";
                    $params = [1, date('Y-m-d H:i:s', $time), $id];
                    $hasUntil = true;
                }

                $sql .= " WHERE id = ?";
                $stmt = $this->link->prepare($sql);
                $res = $stmt->execute($params);

                if ($res && $reason) {
                    // try to write reason to comment column if present
                    $stmt = $this->link->prepare("UPDATE {$this->table} SET comment = CONCAT(IFNULL(comment, ''), ?) WHERE id = ?");
                    $stmt->execute(["\n[ban] " . $reason, $id]);
                }

                return $res;

        }

        public function unban($id) {

                // remove banned flag and banned_until if present
                $sql = "UPDATE {$this->table} SET banned = 0, last_edit = NOW()";
                $check = $this->link->query("SHOW COLUMNS FROM {$this->table} LIKE 'banned_until'");
                if ($check && $check->rowCount() > 0) {
                    $sql .= ", banned_until = NULL";
                }
                $sql .= " WHERE id = ?";
                $stmt = $this->link->prepare($sql);
                return $stmt->execute([$id]);

        }

        private function get_ban_value($id) {

                $stmt = $this->link->prepare("SELECT banned, (CASE WHEN COLUMN_NAME IS NOT NULL THEN (SELECT banned_until FROM {$this->table} WHERE id = ?) ELSE NULL END) AS banned_until, comment FROM {$this->table} LIMIT 1");
                // The above trick cannot check column easily in a portable way, fallback to simple select
                $stmt = $this->link->prepare("SELECT banned, banned_until, comment FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
                return $v ?: null;

        }

    }