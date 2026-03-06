<?php 

	class Auth {

		private $link;
		private $table = 'users';

		public function __construct($link) {

			$this->link = $link;

		}

        public function login($search, $password, $remember = false)
        {
            global $account, $session, $cookie;

            $stmt = $this->link->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$search, $search]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
                return false;
            }

            $stmt = $this->link->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
            $user['last_login'] = date('Y-m-d H:i:s');
            $user['last_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;

            $session->regenerate();
            $session->auth($user['id']);
            $account->set($user);

            // use id() helper to obtain the current user id after auth
            $currentId = $this->id();
            $session->set('role', $user['role']);                       // optioneel, voor toegang
            $session->set('display_name', $user['display_name']);       // optioneel, UI
            $session->set('locale', $user['locale'] ?? 'nl');           // optioneel, taal
            $session->set('_ip', $_SERVER['REMOTE_ADDR']);              // optioneel, security check
            $session->set('_user_agent', $_SERVER['HTTP_USER_AGENT']);  // optioneel, security check

            if ($remember) {
                // 1. Token genereren
                $selector = bin2hex(random_bytes(12));
                $validator = bin2hex(random_bytes(32));
                $hashedValidator = hash('sha256', $validator);
                $expires = date('Y-m-d H:i:s', time() + 30*24*60*60); // 30 dagen

                // 2. Token in aparte tabel opslaan
                $stmt = $this->link->prepare("
                    INSERT INTO remember_tokens
                    (user_id, selector, hashed_validator, expires)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$currentId ?? $user['id'], $selector, $hashedValidator, $expires]);

                // 3. Token in cookie opslaan
                $cookieValue = $selector . ':' . $validator;
                $cookie->set('remember_me', $cookieValue, time() + 30*24*60*60);
            }

            return true;
        }

		public function logout() {

            global $account, $session, $cookie;

            // Remove remember-me token from DB and clear the cookie
            if (!empty($_COOKIE['remember_me'])) {
                $parts = explode(':', $_COOKIE['remember_me'], 2);
                if (count($parts) === 2) {
                    $selector = $parts[0];
                    $stmt = $this->link->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                    $stmt->execute([$selector]);
                }
                if (is_object($cookie) && method_exists($cookie, 'delete')) {
                    $cookie->delete('remember_me');
                } else {
                    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                }
            }

            if ($account) {
                $account->set([]);
            }

            if (is_object($session) && method_exists($session, 'destroy')) {
                $session->destroy();
            } else {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
            }

	}

		public function register($data) {

			if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
				return false;
			}

			// Duplicate check
			$stmt = $this->link->prepare("SELECT 1 FROM {$this->table} WHERE username = ? OR email = ? LIMIT 1");
			$stmt->execute([$data['username'], $data['email']]);
			if ($stmt->fetchColumn()) {
				return false;
			}

			$allowed = [
				'username', 'email', 'first_name', 'last_name', 'display_name',
                'gender', 'birth_date'
			];

			$cols   = ['password', 'status', 'role', 'created_at'];
			$values = [password_hash($data['password'], PASSWORD_DEFAULT), 'active', 'user', date('Y-m-d H:i:s')];

			foreach ($allowed as $col) {
				if (!empty($data[$col])) {
					$cols[]   = $col;
					$values[] = $data[$col];
				}
			}

			$placeholders = implode(', ', array_fill(0, count($cols), '?'));
			$sql = "INSERT INTO {$this->table} (" . implode(', ', $cols) . ") VALUES ({$placeholders})";
			$stmt = $this->link->prepare($sql);
			$result = $stmt->execute($values);
			return $result ? (int) $this->link->lastInsertId() : false;

		}

		public function password_generate($difficulty = 3, $length = 12) {

			$sets = [
				'abcdefghijklmnopqrstuvwxyz',
				'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
				'0123456789',
				'!@#$%^&*()-_=+[]{}|;:,.<>?'
			];
			$use  = array_slice($sets, 0, max(1, min((int) $difficulty, 4)));
			$pool = implode('', $use);

			$password = '';
			foreach ($use as $set) {
				$password .= $set[random_int(0, strlen($set) - 1)];
			}
			for ($i = strlen($password); $i < $length; $i++) {
				$password .= $pool[random_int(0, strlen($pool) - 1)];
			}

			return str_shuffle($password);

		}

		public function password_hash($password) {

			return password_hash($password, PASSWORD_DEFAULT);

		}

		public function password_validate($password, $hash) {

			return password_verify($password, $hash);

		}

		public function password_forgot($email) {

			$stmt = $this->link->prepare("SELECT id FROM {$this->table} WHERE email = ? AND status = 'active' LIMIT 1");
			$stmt->execute([$email]);
			$user = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$user) {
				return false;
			}

			$token   = bin2hex(random_bytes(32));
			$expires = date('Y-m-d H:i:s', time() + 3600);

			$stmt = $this->link->prepare(
				"INSERT INTO password_resets (user_id, token, expires, created_at)
				 VALUES (?, ?, ?, NOW())
				 ON DUPLICATE KEY UPDATE token = VALUES(token), expires = VALUES(expires), created_at = NOW()"
			);
			$stmt->execute([$user['id'], hash('sha256', $token), $expires]);

			return $token; // caller is responsible for sending this to the user

		}

		public function perm_check($account_id, $permission) {

            // Allow checking for the current user when account_id is null
            if (empty($account_id)) {
                $account_id = $this->id();
            }

            $permission_id = $this->perm_get_id($permission);
            if (empty($permission_id)) {
                return false;
            }

            // Check direct account permission
            $stmt = $this->link->prepare("SELECT 1 FROM perm_account_permission WHERE account_id = ? AND permission_id = ? LIMIT 1");
            $stmt->execute([$account_id, $permission_id]);
            if ($stmt->fetch()) {
                return true;
            }

            // Check group membership -> group permissions
            $stmt = $this->link->prepare(
                "SELECT 1 FROM perm_account_group ag
                 JOIN perm_group_permission gp ON ag.group_id = gp.group_id
                 WHERE ag.account_id = ? AND gp.permission_id = ? LIMIT 1"
            );
            $stmt->execute([$account_id, $permission_id]);
            return (bool) $stmt->fetch();

		}

		public function perm_check_page($current_page) {

            $perm = $this->perm_convert($current_page);
            return $this->perm_check(null, $perm);

		}

		public function perm_convert($current_page) {

            // Convert a page/path to a normalized permission name.
            $perm = trim($current_page, "\/ ");
            // strip file extension
            $perm = preg_replace('/\.[a-z0-9]+$/i', '', $perm);
            // convert slashes to dots and remove unsafe characters
            $perm = str_replace('/', '.', $perm);
            $perm = preg_replace('/[^a-z0-9._-]/i', '', $perm);
            return strtolower($perm);

		}

		public function perm_get_id($permission) {

            if (empty($permission)) {
                return null;
            }

            if (is_numeric($permission)) {
                return (int) $permission;
            }

            $stmt = $this->link->prepare("SELECT permission_id FROM perm_permission WHERE permission_name = ? LIMIT 1");
            $stmt->execute([$permission]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['permission_id'] : null;

		}

		// public function api_check($api_key) {

		// 	if (empty($api_key)) {
		// 		return false;
		// 	}

		// 	$stmt = $this->link->prepare("SELECT id FROM {$this->table} WHERE api_key = ? AND status = 'active' LIMIT 1");
		// 	$stmt->execute([$api_key]);
		// 	$row = $stmt->fetch(PDO::FETCH_ASSOC);
		// 	return $row ? (int) $row['id'] : false;

		// }

        public function perm_groups() {

            $stmt = $this->link->query("SELECT * FROM perm_group ORDER BY group_name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

        public function perm_permissions() {

            $stmt = $this->link->query("SELECT * FROM perm_permission ORDER BY permission_name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        }

		public function id() {


            global $session;

            // Ensure session or remember-me cookie is checked/re-established
            $this->check();

            // Try session object getter if available
            if (is_object($session) && method_exists($session, 'get')) {
                $id = $session->get('user_id');
                if (!empty($id)) {
                    return $id;
                }
            }

            // Fallback to array access / superglobal
            if (is_array($session) && isset($session['user_id'])) {
                return $session['user_id'];
            }

            if (isset($_SESSION['user_id'])) {
                return $_SESSION['user_id'];
            }

            return null;

        }

		public function check() {

            global $account, $session, $cookie;

            // 1) Check session
            // Support Session object, array or raw $_SESSION
            if (is_object($session)) {
                if (method_exists($session, 'has') && $session->has('user_id')) {
                    return true;
                }
                if (method_exists($session, 'get') && $session->get('user_id')) {
                    return true;
                }
            } elseif (is_array($session) && isset($session['user_id'])) {
                return true;
            } elseif (isset($_SESSION['user_id'])) {
                return true;
            }

            // 2) Check remember-me cookie
            if (!empty($_COOKIE['remember_me'])) {
                $parts = explode(':', $_COOKIE['remember_me'], 2);
                if (count($parts) === 2) {
                    list($selector, $validator) = $parts;

                    $stmt = $this->link->prepare("SELECT * FROM remember_tokens WHERE selector = ? LIMIT 1");
                    $stmt->execute([$selector]);
                    $token = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($token && strtotime($token['expires']) > time()) {
                        $hash = hash('sha256', $validator);
                        if (hash_equals($token['hashed_validator'], $hash)) {
                            // load user
                            $stmt = $this->link->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                            $stmt->execute([$token['user_id']]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($user && $user['status'] === 'active') {
                                // re-establish session using Session API when available
                                if (is_object($session) && method_exists($session, 'regenerate')) {
                                    $session->regenerate();
                                }
                                if (is_object($session) && method_exists($session, 'auth')) {
                                    $session->auth($user['id']);
                                } elseif (is_object($session) && method_exists($session, 'set')) {
                                    $session->set('user_id', $user['id']);
                                } elseif (is_array($session)) {
                                    $session['user_id'] = $user['id'];
                                } else {
                                    $_SESSION['user_id'] = $user['id'];
                                }
                                if ($account) {
                                    $account->set($user);
                                }
                                return true;
                            }
                        }
                    }
                    // invalid token: remove cookie and token row if present
                    setcookie('remember_me', '', time() - 3600, "/", "", false, true);
                    $stmt = $this->link->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                    $stmt->execute([$selector]);
                }
            }

            return false;

        }

	}