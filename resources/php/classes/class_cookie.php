<?php

class cookie {

    private $link;
    private $table = 'mw_cookies';
    
    // Encryptie instellingen
    private $encryption_key;
    private $cipher = 'AES-256-GCM';
    
    // Standaard cookie instellingen
    private $defaults = [
        'expires' => 0,           // 0 = session cookie
        'path' => '/',
        'domain' => '',
        'secure' => true,         // Alleen HTTPS
        'httponly' => true,       // Niet toegankelijk via JavaScript
        'samesite' => 'Strict'    // CSRF bescherming
    ];

    public function __construct($link, $encryption_key = null) {
        $this->link = $link;
        
        // Genereer of gebruik provided encryption key
        if ($encryption_key) {
            $this->encryption_key = $encryption_key;
        } else {
            // Haal uit config of environment
            $this->encryption_key = $this->getEncryptionKey();
        }
    }

    /**
     * Maak nieuwe cookie aan
     */
    public function set($name, $value, $options = []) {
        try {
            // Merge opties met defaults
            $options = array_merge($this->defaults, $options);
            
            // Bereken expiry timestamp
            $expires = $this->calculateExpiry($options['expires']);
            
            // Encrypt waarde voor veilige opslag
            $encryptedValue = $this->encrypt($value);
            
            // Genereer unieke cookie ID voor database tracking
            $cookieId = $this->generateCookieId();
            
            // Sla op in database voor server-side validatie
            $sql = "INSERT INTO {$this->table} 
                    (cookie_id, name, value, expires, path, domain, 
                     secure, httponly, samesite, ip, user_agent, created_at) 
                    VALUES 
                    (:cookie_id, :name, :value, :expires, :path, :domain,
                     :secure, :httponly, :samesite, :ip, :user_agent, NOW())";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':cookie_id' => $cookieId,
                ':name' => $name,
                ':value' => $encryptedValue,
                ':expires' => date('Y-m-d H:i:s', $expires),
                ':path' => $options['path'],
                ':domain' => $options['domain'],
                ':secure' => $options['secure'] ? 1 : 0,
                ':httponly' => $options['httponly'] ? 1 : 0,
                ':samesite' => $options['samesite'],
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Zet daadwerkelijke browser cookie
            $cookieValue = $cookieId . '|' . base64_encode($encryptedValue);
            $signedValue = $this->sign($cookieValue);
            
            $this->setBrowserCookie($name, $signedValue, [
                'expires' => $expires,
                'path' => $options['path'],
                'domain' => $options['domain'],
                'secure' => $options['secure'],
                'httponly' => $options['httponly'],
                'samesite' => $options['samesite']
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Cookie set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal cookie op
     */
    public function get($name, $default = null) {
        try {
            // Check of cookie bestaat in browser
            if (!isset($_COOKIE[$name])) {
                return $default;
            }
            
            $signedValue = $_COOKIE[$name];
            
            // Verifieer signature
            if (!$this->verify($signedValue)) {
                $this->delete($name);
                return $default;
            }
            
            // Haal cookie ID en waarde op
            $cookieValue = $this->unsign($signedValue);
            $parts = explode('|', $cookieValue, 2);
            
            if (count($parts) !== 2) {
                $this->delete($name);
                return $default;
            }
            
            list($cookieId, $encryptedValue) = $parts;
            $encryptedValue = base64_decode($encryptedValue);
            
            // Valideer tegen database
            $sql = "SELECT * FROM {$this->table} 
                    WHERE cookie_id = :cookie_id 
                    AND name = :name 
                    AND expires > NOW()
                    AND (ip IS NULL OR ip = :ip)
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':cookie_id' => $cookieId,
                ':name' => $name,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $dbCookie = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dbCookie) {
                $this->delete($name);
                return $default;
            }
            
            // Decrypt waarde
            $value = $this->decrypt($encryptedValue);
            
            // Update last_accessed
            $this->touch($cookieId);
            
            return $value;
            
        } catch (Exception $e) {
            error_log("Cookie get error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Verwijder cookie
     */
    public function delete($name) {
        try {
            // Haal cookie ID op uit browser
            if (isset($_COOKIE[$name])) {
                $signedValue = $_COOKIE[$name];
                
                if ($this->verify($signedValue)) {
                    $cookieValue = $this->unsign($signedValue);
                    $parts = explode('|', $cookieValue, 2);
                    
                    if (count($parts) === 2) {
                        // Verwijder uit database
                        $sql = "DELETE FROM {$this->table} WHERE cookie_id = :cookie_id";
                        $stmt = $this->link->prepare($sql);
                        $stmt->execute([':cookie_id' => $parts[0]]);
                    }
                }
                
                // Verwijder browser cookie (verlopen in het verleden)
                $this->setBrowserCookie($name, '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Cookie delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check of cookie bestaat
     */
    public function exists($name) {
        return isset($_COOKIE[$name]) && $this->get($name) !== null;
    }

    /**
     * Update cookie waarde (behoudt zelfde ID en instellingen)
     */
    public function update($name, $newValue) {
        try {
            if (!isset($_COOKIE[$name])) {
                return false;
            }
            
            $signedValue = $_COOKIE[$name];
            
            if (!$this->verify($signedValue)) {
                return false;
            }
            
            $cookieValue = $this->unsign($signedValue);
            $parts = explode('|', $cookieValue, 2);
            
            if (count($parts) !== 2) {
                return false;
            }
            
            $cookieId = $parts[0];
            $encryptedValue = $this->encrypt($newValue);
            
            // Update database
            $sql = "UPDATE {$this->table} 
                    SET value = :value, 
                        last_accessed = NOW(),
                        access_count = access_count + 1
                    WHERE cookie_id = :cookie_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':value' => $encryptedValue,
                ':cookie_id' => $cookieId
            ]);
            
            // Update browser cookie
            $newCookieValue = $cookieId . '|' . base64_encode($encryptedValue);
            $signedNewValue = $this->sign($newCookieValue);
            
            // Haal huidige instellingen op uit database
            $sql = "SELECT * FROM {$this->table} WHERE cookie_id = :cookie_id LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':cookie_id' => $cookieId]);
            $dbCookie = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dbCookie) {
                $this->setBrowserCookie($name, $signedNewValue, [
                    'expires' => strtotime($dbCookie['expires']),
                    'path' => $dbCookie['path'],
                    'domain' => $dbCookie['domain'],
                    'secure' => (bool)$dbCookie['secure'],
                    'httponly' => (bool)$dbCookie['httponly'],
                    'samesite' => $dbCookie['samesite']
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Cookie update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verleng cookie levensduur
     */
    public function extend($name, $additionalTime) {
        try {
            if (!isset($_COOKIE[$name])) {
                return false;
            }
            
            $signedValue = $_COOKIE[$name];
            
            if (!$this->verify($signedValue)) {
                return false;
            }
            
            $cookieValue = $this->unsign($signedValue);
            $parts = explode('|', $cookieValue, 2);
            
            if (count($parts) !== 2) {
                return false;
            }
            
            $cookieId = $parts[0];
            $newExpires = time() + $additionalTime;
            
            // Update database
            $sql = "UPDATE {$this->table} 
                    SET expires = :expires 
                    WHERE cookie_id = :cookie_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':expires' => date('Y-m-d H:i:s', $newExpires),
                ':cookie_id' => $cookieId
            ]);
            
            // Update browser cookie met nieuwe expiry
            $this->setBrowserCookie($name, $signedValue, [
                'expires' => $newExpires,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Cookie extend error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal alle cookies van huidige gebruiker/sessie
     */
    public function getAll() {
        try {
            $sql = "SELECT name, value, expires, created_at, last_accessed, access_count 
                    FROM {$this->table} 
                    WHERE ip = :ip 
                    AND expires > NOW()
                    ORDER BY created_at DESC";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            
            $cookies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decrypt waarden
            foreach ($cookies as &$cookie) {
                $cookie['value'] = $this->decrypt($cookie['value']);
            }
            
            return $cookies;
            
        } catch (Exception $e) {
            error_log("Get all cookies error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verwijder alle cookies (logout functionaliteit)
     */
    public function clearAll() {
        try {
            // Haal alle cookies op voor dit IP
            $sql = "SELECT cookie_id, name FROM {$this->table} 
                    WHERE ip = :ip 
                    OR (user_agent = :user_agent AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $cookies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verwijder elke cookie
            foreach ($cookies as $cookieData) {
                $this->delete($cookieData['name']);
            }
            
            // Verwijder ook uit database
            $sql = "DELETE FROM {$this->table} 
                    WHERE ip = :ip 
                    OR (user_agent = :user_agent AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Clear all cookies error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Flash message cookie (automatisch verwijderd na ophalen)
     */
    public function flash($name, $value = null) {
        if ($value !== null) {
            // Set flash cookie (1 uur geldig, wordt verwijderd na ophalen)
            return $this->set($name, $value, ['expires' => 3600]);
        } else {
            // Get en verwijder
            $value = $this->get($name);
            $this->delete($name);
            return $value;
        }
    }

    /**
     * CSRF token genereren en opslaan in cookie
     */
    public function csrfToken($name = 'csrf_token') {
        $token = bin2hex(random_bytes(32));
        $this->set($name, $token, ['expires' => 3600]); // 1 uur geldig
        return $token;
    }

    /**
     * CSRF token valideren
     */
    public function csrfValidate($token, $name = 'csrf_token') {
        $storedToken = $this->get($name);
        if (!$storedToken) {
            return false;
        }
        
        $valid = hash_equals($storedToken, $token);
        
        // Optioneel: verwijder na gebruik (one-time use)
        // $this->delete($name);
        
        return $valid;
    }

    /**
     * Encryptie methoden
     */
    private function encrypt($data) {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '', // AAD (additional authenticated data)
            16  // Tag length
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }

    private function decrypt($data) {
        $data = base64_decode($data);
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $tagLength = 16;
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $ciphertext = substr($data, $ivLength + $tagLength);
        
        return openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

    /**
     * Signing voor integriteit (HMAC)
     */
    private function sign($data) {
        $signature = hash_hmac('sha256', $data, $this->encryption_key);
        return base64_encode($signature) . '|' . base64_encode($data);
    }

    private function verify($signedData) {
        $parts = explode('|', $signedData, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        $signature = base64_decode($parts[0]);
        $data = base64_decode($parts[1]);
        
        $expectedSignature = hash_hmac('sha256', $data, $this->encryption_key, true);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function unsign($signedData) {
        $parts = explode('|', $signedData, 2);
        return base64_decode($parts[1]);
    }

    /**
     * Helper methoden
     */
    private function generateCookieId() {
        return bin2hex(random_bytes(16));
    }

    private function calculateExpiry($expires) {
        if (is_numeric($expires)) {
            return time() + $expires;
        }
        return $expires;
    }

    private function setBrowserCookie($name, $value, $options) {
        // PHP 7.3+ style
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, [
                'expires' => $options['expires'],
                'path' => $options['path'],
                'domain' => $options['domain'],
                'secure' => $options['secure'],
                'httponly' => $options['httponly'],
                'samesite' => $options['samesite']
            ]);
        } else {
            // Fallback voor oudere PHP versies
            setcookie(
                $name,
                $value,
                $options['expires'],
                $options['path'] . '; SameSite=' . $options['samesite'],
                $options['domain'],
                $options['secure'],
                $options['httponly']
            );
        }
    }

    private function touch($cookieId) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET last_accessed = NOW(), 
                        access_count = access_count + 1 
                    WHERE cookie_id = :cookie_id";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':cookie_id' => $cookieId]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }

    private function getEncryptionKey() {
        // Haal uit environment of config file
        $key = getenv('COOKIE_ENCRYPTION_KEY');
        
        if (!$key) {
            // Genereer nieuwe key (opslaan in .env file!)
            $key = base64_encode(random_bytes(32));
            error_log("WARNING: Nieuwe encryptie key gegenereerd. Sla deze op in je .env file: " . $key);
        }
        
        return base64_decode($key);
    }

    /**
     * Cleanup oude cookies
     */
    public function cleanup() {
        try {
            $sql = "DELETE FROM {$this->table} WHERE expires < NOW()";
            $stmt = $this->link->prepare($sql);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Cookie cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}