<?php

class Auth {

    private $link;
    private $table = 'mw_users';
    private $current_user = null;
    private $session_name = 'mw_auth_session';
    private $cookie_name = 'mw_remember';

    public function __construct($link) {
        $this->link = $link;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Inloggen met username/email en wachtwoord
     */
    public function login($search, $password, $remember = false) {
        try {
            // Zoek gebruiker op email of username
            $sql = "SELECT * FROM {$this->table} 
                    WHERE (email = :search OR username = :search) 
                    AND trash = 0 
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':search' => $search]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'error' => 'Gebruiker niet gevonden'];
            }

            // Controleer of account gebanned is
            if ($this->isBanned($user['id'])) {
                return ['success' => false, 'error' => 'Account is geblokkeerd'];
            }

            // Valideer wachtwoord
            if (!$this->password_validate($password, $user['password'])) {
                $this->logFailedAttempt($user['id']);
                return ['success' => false, 'error' => 'Ongeldig wachtwoord'];
            }

            // Reset failed attempts
            $this->resetFailedAttempts($user['id']);

            // Start sessie
            $sessionData = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'login_time' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];

            $_SESSION[$this->session_name] = $sessionData;

            // Remember me cookie
            if ($remember) {
                $token = $this->generateToken();
                $expires = time() + (30 * 24 * 60 * 60);
                
                setcookie($this->cookie_name, $token, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                $this->saveRememberToken($user['id'], $token);
            }

            $this->updateLastLogin($user['id']);
            $this->current_user = $user;
            
            return [
                'success' => true, 
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ]
            ];

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Inlogfout'];
        }
    }

    /**
     * Uitloggen
     */
    public function logout() {
        if (isset($_SESSION[$this->session_name])) {
            unset($_SESSION[$this->session_name]);
        }

        if (isset($_COOKIE[$this->cookie_name])) {
            setcookie($this->cookie_name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            $this->deleteRememberToken($_COOKIE[$this->cookie_name]);
        }

        session_destroy();
        $this->current_user = null;

        return true;
    }

    /**
     * Registratie nieuwe gebruiker
     */
    public function register($data) {
        try {
            if (empty($data['email']) || empty($data['username']) || empty($data['password'])) {
                return ['success' => false, 'error' => 'Vereiste velden ontbreken'];
            }

            $sql = "SELECT id FROM {$this->table} 
                    WHERE email = :email OR username = :username 
                    LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':email' => $data['email'],
                ':username' => $data['username']
            ]);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Email of username bestaat al'];
            }

            $hashedPassword = $this->password_hash($data['password']);

            $sql = "INSERT INTO {$this->table} 
                    (email, username, password, created, last_edit) 
                    VALUES (:email, :username, :password, NOW(), NOW())";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':email' => $data['email'],
                ':username' => $data['username'],
                ':password' => $hashedPassword
            ]);

            $userId = $this->link->lastInsertId();

            // Optioneel: voeg toe aan standaard groep
            if (!empty($data['default_group'])) {
                $this->addToGroup($userId, $data['default_group']);
            }

            return [
                'success' => true, 
                'user_id' => $userId,
                'message' => 'Registratie succesvol'
            ];

        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Registratiefout'];
        }
    }

    /**
     * Genereer veilig wachtwoord
     */
    public function password_generate($difficulty = 3, $length = 12) {
        $sets = [
            1 => 'abcdefghijklmnopqrstuvwxyz',
            2 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            3 => '0123456789',
            4 => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        $password = '';
        $chars = '';

        for ($i = 1; $i <= $difficulty && $i <= 4; $i++) {
            $chars .= $sets[$i];
            $password .= $sets[$i][random_int(0, strlen($sets[$i]) - 1)];
        }

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Hash wachtwoord
     */
    public function password_hash($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Valideer wachtwoord
     */
    public function password_validate($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Wachtwoord vergeten
     */
    public function password_forgot($email) {
        try {
            $sql = "SELECT id, username FROM {$this->table} 
                    WHERE email = :email AND trash = 0 
                    LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => true, 'message' => 'Als dit email bestaat, ontvang je een reset link'];
            }

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $sql = "INSERT INTO mw_password_resets (user_id, token, expires, created) 
                    VALUES (:user_id, :token, :expires, NOW())";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':user_id' => $user['id'],
                ':token' => hash('sha256', $token),
                ':expires' => $expires
            ]);

            $resetLink = "https://yoursite.com/reset-password?token=" . $token;
            $this->sendResetEmail($email, $user['username'], $resetLink);

            return ['success' => true, 'message' => 'Reset instructies verstuurd'];

        } catch (PDOException $e) {
            error_log("Password forgot error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fout bij verwerken'];
        }
    }

    /**
     * Check permissie voor gebruiker - GEBRUIKT perm_account_permission
     */
    public function perm_check($account_id, $permission) {
        try {
            // Check directe permissie op account (perm_account_permission)
            $sql = "SELECT COUNT(*) as has_perm 
                    FROM perm_account_permission pap
                    INNER JOIN perm_permission pp ON pap.permission_id = pp.id
                    WHERE pap.account_id = :account_id 
                    AND pp.name = :permission";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':account_id' => $account_id,
                ':permission' => $permission
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['has_perm'] > 0) {
                return true;
            }

            // Check permissie via groepen (perm_account_group -> perm_group_permission)
            $sql = "SELECT COUNT(*) as has_perm 
                    FROM perm_account_group pag
                    INNER JOIN perm_group_permission pgp ON pag.group_id = pgp.group_id
                    INNER JOIN perm_permission pp ON pgp.permission_id = pp.id
                    WHERE pag.account_id = :account_id 
                    AND pp.name = :permission";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':account_id' => $account_id,
                ':permission' => $permission
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['has_perm'] > 0;

        } catch (PDOException $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check permissie voor huidige pagina
     */
    public function perm_check_page($current_page) {
        $userId = $this->id();
        
        if (!$userId) return false;

        $permission = $this->perm_convert($current_page);
        
        return $this->perm_check($userId, $permission);
    }

    /**
     * Converteer pagina naam naar permissie
     */
    public function perm_convert($current_page) {
        $mapping = [
            'admin.php' => 'admin.access',
            'users.php' => 'users.manage',
            'settings.php' => 'settings.edit',
            'content.php' => 'content.manage',
            'dashboard.php' => 'dashboard.view'
        ];

        return $mapping[$current_page] ?? 'site.view';
    }

    /**
     * Haal permissie ID op uit perm_permission tabel
     */
    public function perm_get_id($permission) {
        try {
            $sql = "SELECT id FROM perm_permission WHERE name = :name LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':name' => $permission]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;

        } catch (PDOException $e) {
            error_log("Get permission ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Haal alle permissies van gebruiker op
     */
    public function perm_get_all($account_id) {
        try {
            // Directe permissies
            $sql = "SELECT pp.name, pp.description 
                    FROM perm_account_permission pap
                    INNER JOIN perm_permission pp ON pap.permission_id = pp.id
                    WHERE pap.account_id = :account_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':account_id' => $account_id]);
            $directPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Groep permissies
            $sql = "SELECT DISTINCT pp.name, pp.description 
                    FROM perm_account_group pag
                    INNER JOIN perm_group_permission pgp ON pag.group_id = pgp.group_id
                    INNER JOIN perm_permission pp ON pgp.permission_id = pp.id
                    WHERE pag.account_id = :account_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':account_id' => $account_id]);
            $groupPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_merge($directPerms, $groupPerms);

        } catch (PDOException $e) {
            error_log("Get all permissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Haal alle groepen van gebruiker op
     */
    public function perm_get_groups($account_id) {
        try {
            $sql = "SELECT pg.id, pg.name, pg.description 
                    FROM perm_group pg
                    INNER JOIN perm_account_group pag ON pg.id = pag.group_id
                    WHERE pag.account_id = :account_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':account_id' => $account_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get user groups error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Valideer API key
     */
    public function api_check($api_key) {
        try {
            $sql = "SELECT user_id, permissions FROM mw_api_keys 
                    WHERE api_key = :api_key 
                    AND active = 1 
                    AND (expires IS NULL OR expires > NOW())
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':api_key' => hash('sha256', $api_key)]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("API check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal huidige gebruiker ID op
     */
    public function id() {
        if ($this->current_user) {
            return $this->current_user['id'];
        }

        if (isset($_SESSION[$this->session_name]['user_id'])) {
            return $_SESSION[$this->session_name]['user_id'];
        }

        if (isset($_COOKIE[$this->cookie_name])) {
            $userId = $this->validateRememberToken($_COOKIE[$this->cookie_name]);
            if ($userId) {
                $this->restoreSession($userId);
                return $userId;
            }
        }

        return null;
    }

    /**
     * Check of gebruiker is ingelogd
     */
    public function check() {
        return $this->id() !== null;
    }

    /**
     * Haal huidige gebruiker data op
     */
    public function user() {
        $userId = $this->id();
        if (!$userId) return null;

        if ($this->current_user) {
            return $this->current_user;
        }

        try {
            $sql = "SELECT id, username, email, profile_picture, created 
                    FROM {$this->table} 
                    WHERE id = :id LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $userId]);
            
            $this->current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $this->current_user;

        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Voeg gebruiker toe aan groep
     */
    public function addToGroup($account_id, $group_id) {
        try {
            $sql = "INSERT IGNORE INTO perm_account_group (account_id, group_id) 
                    VALUES (:account_id, :group_id)";
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([
                ':account_id' => $account_id,
                ':group_id' => $group_id
            ]);
        } catch (PDOException $e) {
            error_log("Add to group error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verwijder gebruiker uit groep
     */
    public function removeFromGroup($account_id, $group_id) {
        try {
            $sql = "DELETE FROM perm_account_group 
                    WHERE account_id = :account_id AND group_id = :group_id";
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([
                ':account_id' => $account_id,
                ':group_id' => $group_id
            ]);
        } catch (PDOException $e) {
            error_log("Remove from group error: " . $e->getMessage());
            return false;
        }
    }

    // Helper methods...
    private function isBanned($userId) {
        $sql = "SELECT banned FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['banned'] == 0) return false;
        if ($result['banned'] == 1) return true;
        
        return time() < $result['banned'];
    }

    private function generateToken() {
        return bin2hex(random_bytes(32));
    }

    private function logFailedAttempt($userId) {
        $sql = "INSERT INTO mw_login_attempts (user_id, ip, attempted_at) 
                VALUES (:user_id, :ip, NOW())";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }

    private function resetFailedAttempts($userId) {
        $sql = "DELETE FROM mw_login_attempts WHERE user_id = :user_id";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    }

    private function saveRememberToken($userId, $token) {
        $sql = "INSERT INTO mw_remember_tokens (user_id, token, expires, created) 
                VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => hash('sha256', $token)
        ]);
    }

    private function deleteRememberToken($token) {
        $sql = "DELETE FROM mw_remember_tokens WHERE token = :token";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':token' => hash('sha256', $token)]);
    }

    private function validateRememberToken($token) {
        $sql = "SELECT user_id FROM mw_remember_tokens 
                WHERE token = :token AND expires > NOW() 
                LIMIT 1";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':token' => hash('sha256', $token)]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['user_id'] : false;
    }

    private function restoreSession($userId) {
        $sql = "SELECT id, username, email FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION[$this->session_name] = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'login_time' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];
            $this->current_user = $user;
        }
    }

    private function updateLastLogin($userId) {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->link->prepare($sql);
        $stmt->execute([':id' => $userId]);
    }

    private function sendResetEmail($email, $username, $link) {
        $subject = "Wachtwoord reset aanvraag";
        $message = "Beste $username,\n\nKlik op deze link om je wachtwoord te resetten:\n$link\n\nDeze link is 1 uur geldig.";
    }
}