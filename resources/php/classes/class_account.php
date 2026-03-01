<?php

class account {

    private $link;

    public $id;
    public $email;
    public $username;
    public $password;
    public $profile_picture;
    public $banned;
    public $trash;
    public $date_of_birth;
    public $street;
    public $street_num;
    public $postal;
    public $city;
    public $country;
    public $gender;
    public $comment;
    public $bio;
    public $created;
    public $last_edit;
    public $last_edit_by;

    public $auth = 'auth';

    private $table = 'mw_users';

    public function __construct($link) {
        $this->link = $link;
    }

    /**
     * Maak een nieuw account aan
     */
    public function set($data) {
        try {
            // Hash het wachtwoord
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            
            $sql = "INSERT INTO {$this->table} 
                    (email, username, password, profile_picture, banned, trash, 
                     date_of_birth, street, street_num, postal, city, country, 
                     gender, comment, bio, created, last_edit, last_edit_by) 
                    VALUES 
                    (:email, :username, :password, :profile_picture, 0, 0,
                     :date_of_birth, :street, :street_num, :postal, :city, :country,
                     :gender, :comment, :bio, NOW(), NOW(), :last_edit_by)";
            
            $stmt = $this->link->prepare($sql);
            
            $stmt->execute([
                ':email' => $data['email'],
                ':username' => $data['username'],
                ':password' => $hashedPassword,
                ':profile_picture' => $data['profile_picture'] ?? null,
                ':date_of_birth' => $data['date_of_birth'] ?? null,
                ':street' => $data['street'] ?? null,
                ':street_num' => $data['street_num'] ?? null,
                ':postal' => $data['postal'] ?? null,
                ':city' => $data['city'] ?? null,
                ':country' => $data['country'] ?? null,
                ':gender' => $data['gender'] ?? null,
                ':comment' => $data['comment'] ?? null,
                ':bio' => $data['bio'] ?? null,
                ':last_edit_by' => $data['last_edit_by'] ?? null
            ]);
            
            $this->id = $this->link->lastInsertId();
            return $this->id;
            
        } catch (PDOException $e) {
            error_log("Account creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal account op basis van ID
     */
    public function get($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = :id AND trash = 0 LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->populate($data);
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Account get error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Controleer of account bestaat (op email of username)
     */
    public function check($identifier) {
        try {
            $sql = "SELECT id FROM {$this->table} 
                    WHERE (email = :identifier OR username = :identifier) 
                    AND trash = 0 
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':identifier' => $identifier]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
            
        } catch (PDOException $e) {
            error_log("Account check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update account data
     */
    public function put($data) {
        try {
            $fields = [];
            $params = [':id' => $data['id']];
            
            // Bouw dynamische update query
            $allowedFields = [
                'email', 'username', 'profile_picture', 'date_of_birth',
                'street', 'street_num', 'postal', 'city', 'country',
                'gender', 'comment', 'bio', 'last_edit_by'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Apart wachtwoord behandelen (hashen)
            if (!empty($data['password'])) {
                $fields[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }
            
            $fields[] = "last_edit = NOW()";
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
            
            $stmt = $this->link->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Account update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verplaats naar prullenbak (soft delete)
     */
    public function retrieve($id) {
        return $this->delete($id, true);
    }

    /**
     * Kopieer account
     */
    private function copy($id) {
        try {
            // Haal originele data op
            $sql = "SELECT * FROM {$this->table} WHERE id = :id";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) return false;
            
            // Maak kopie met aangepaste username
            unset($data['id']);
            $data['username'] = $data['username'] . '_copy_' . time();
            $data['email'] = 'copy_' . time() . '_' . $data['email'];
            $data['created'] = date('Y-m-d H:i:s');
            $data['last_edit'] = date('Y-m-d H:i:s');
            
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            $stmt = $this->link->prepare($sql);
            
            return $stmt->execute($data);
            
        } catch (PDOException $e) {
            error_log("Account copy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verwijder account (hard of soft delete)
     */
    public function delete($id, $soft = true) {
        try {
            if ($soft) {
                // Soft delete - verplaats naar prullenbak
                $sql = "UPDATE {$this->table} 
                        SET trash = 1, last_edit = NOW() 
                        WHERE id = :id AND trash = 0";
            } else {
                // Hard delete - permanent verwijderen
                $sql = "DELETE FROM {$this->table} WHERE id = :id";
            }
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Account delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal trash status op
     */
    private function get_trash_status($id) {
        try {
            $sql = "SELECT trash FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['trash'] : null;
            
        } catch (PDOException $e) {
            error_log("Get trash status error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Herstel account uit prullenbak
     */
    public function restore($id) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET trash = 0, last_edit = NOW() 
                    WHERE id = :id AND trash = 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Account restore error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Zoek accounts
     */
    public function find($search) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE (username LIKE :search 
                    OR email LIKE :search 
                    OR city LIKE :search 
                    OR country LIKE :search)
                    AND trash = 0";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':search' => '%' . $search . '%']);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Account find error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Haal alle accounts op
     */
    public function get_all($wildcard = false) {
        try {
            $sql = "SELECT * FROM {$this->table}";
            
            if (!$wildcard) {
                $sql .= " WHERE trash = 0";
            }
            
            $sql .= " ORDER BY created DESC";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get all accounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Haal groepen van gebruiker op
     */
    public function get_groups($id) {
        try {
            // Aannemend dat er een koppeltabel is: mw_user_groups
            $sql = "SELECT g.* FROM mw_groups g
                    INNER JOIN mw_user_groups ug ON g.id = ug.group_id
                    WHERE ug.user_id = :user_id";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':user_id' => $id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Get groups error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ban een gebruiker
     */
    public function ban($id, $reason = null, $time = null) {
        try {
            $banValue = $time ? time() + $time : 1; // 1 = permanent
            
            $sql = "UPDATE {$this->table} 
                    SET banned = :banned, 
                        comment = CONCAT(IFNULL(comment, ''), '\nBan reden: ', :reason, ' (tot: ', :until, ')'),
                        last_edit = NOW()
                    WHERE id = :id";
            
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([
                ':banned' => $banValue,
                ':reason' => $reason ?? 'Geen reden opgegeven',
                ':until' => $time ? date('Y-m-d H:i:s', time() + $time) : 'Permanent',
                ':id' => $id
            ]);
            
        } catch (PDOException $e) {
            error_log("Ban error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unban gebruiker
     */
    public function unban($id) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET banned = 0, 
                        comment = CONCAT(IFNULL(comment, ''), '\nUnbanned op: ', NOW()),
                        last_edit = NOW()
                    WHERE id = :id";
            
            $stmt = $this->link->prepare($sql);
            return $stmt->execute([':id' => $id]);
            
        } catch (PDOException $e) {
            error_log("Unban error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Haal ban waarde op
     */
    private function get_ban_value($id) {
        try {
            $sql = "SELECT banned FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['banned'] : null;
            
        } catch (PDOException $e) {
            error_log("Get ban value error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check of gebruiker gebanned is
     */
    public function is_banned($id) {
        $banValue = $this->get_ban_value($id);
        
        if (!$banValue || $banValue == 0) {
            return false;
        }
        
        // Permanent ban
        if ($banValue == 1) {
            return true;
        }
        
        // Tijdelijke ban checken
        return time() < $banValue;
    }

    /**
     * Login functionaliteit
     */
    public function login($identifier, $password) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE (email = :identifier OR username = :identifier) 
                    AND trash = 0 
                    LIMIT 1";
            
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':identifier' => $identifier]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($this->is_banned($user['id'])) {
                    return ['success' => false, 'error' => 'Account is geblokkeerd'];
                }
                
                $this->populate($user);
                return ['success' => true, 'user' => $user];
            }
            
            return ['success' => false, 'error' => 'Ongeldige inloggegevens'];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Inlogfout'];
        }
    }

    /**
     * Vul object eigenschappen met data
     */
    private function populate($data) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}