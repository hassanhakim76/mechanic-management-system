<?php
/**
 * User Model
 * Handles user authentication and management
 */

class User {
    private $db;
    private $lastError = '';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $sql = "SELECT u.*, r.role_name,
                       e.Display AS employee_display,
                       e.Position AS employee_position,
                       e.Status AS employee_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.employee_id = e.EmployeeID
                WHERE u.username = ? AND u.is_active = 1";
        
        $user = $this->db->querySingle($sql, [$username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $this->updateLastLogin($user['user_id']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE user_id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Get user by ID
     */
    public function getById($userId) {
        $sql = "SELECT u.*, r.role_name,
                       e.Display AS employee_display,
                       e.Position AS employee_position,
                       e.Status AS employee_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.employee_id = e.EmployeeID
                WHERE u.user_id = ?";
        
        return $this->db->querySingle($sql, [$userId]);
    }
    
    /**
     * Get user by username
     */
    public function getByUsername($username) {
        $sql = "SELECT u.*, r.role_name,
                       e.Display AS employee_display,
                       e.Position AS employee_position,
                       e.Status AS employee_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.employee_id = e.EmployeeID
                WHERE u.username = ?";
        
        return $this->db->querySingle($sql, [$username]);
    }

    public function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT user_id FROM users WHERE username = ?";
        $params = [$username];

        if ($excludeUserId !== null) {
            $sql .= " AND user_id <> ?";
            $params[] = (int)$excludeUserId;
        }

        return (bool)$this->db->querySingle($sql, $params);
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        $sql = "INSERT INTO users (username, password_hash, role_id, employee_id, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        return $this->db->insert($sql, [
            $data['username'],
            $passwordHash,
            $data['role_id'],
            !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            isset($data['is_active']) ? $data['is_active'] : 1
        ]);
    }
    
    /**
     * Update user
     */
    public function update($userId, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['username'])) {
            $fields[] = "username = ?";
            $params[] = $data['username'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        }
        
        if (isset($data['role_id'])) {
            $fields[] = "role_id = ?";
            $params[] = $data['role_id'];
        }

        if (array_key_exists('employee_id', $data)) {
            $fields[] = "employee_id = ?";
            $params[] = !empty($data['employee_id']) ? (int)$data['employee_id'] : null;
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?";
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Delete user (soft delete by setting is_active = 0)
     */
    public function delete($userId) {
        $sql = "UPDATE users SET is_active = 0 WHERE user_id = ?";
        return $this->db->execute($sql, [$userId]);
    }

    public function setActive($userId, $isActive) {
        $sql = "UPDATE users SET is_active = ? WHERE user_id = ?";
        return $this->db->execute($sql, [(int)$isActive, (int)$userId]);
    }
    
    /**
     * Get all users
     */
    public function getAll() {
        $sql = "SELECT u.*, r.role_name,
                       e.Display AS employee_display,
                       e.Position AS employee_position,
                       e.Status AS employee_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.employee_id = e.EmployeeID
                ORDER BY u.is_active DESC, r.role_id ASC, u.username ASC";
        
        return $this->db->query($sql);
    }
    
    /**
     * Get active users
     */
    public function getActive() {
        $sql = "SELECT u.*, r.role_name,
                       e.Display AS employee_display,
                       e.Position AS employee_position,
                       e.Status AS employee_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.employee_id = e.EmployeeID
                WHERE u.is_active = 1
                ORDER BY u.username";
        
        return $this->db->query($sql);
    }

    public function getRoles() {
        $sql = "SELECT role_id, role_name FROM roles ORDER BY role_id";
        return $this->db->query($sql) ?: [];
    }

    public function roleExists($roleId) {
        return (bool)$this->db->querySingle(
            "SELECT role_id FROM roles WHERE role_id = ?",
            [(int)$roleId]
        );
    }

    public function countActiveAdmins($excludeUserId = null) {
        $sql = "SELECT COUNT(*) AS total FROM users WHERE role_id = ? AND is_active = 1";
        $params = [ROLE_ADMIN];

        if ($excludeUserId !== null) {
            $sql .= " AND user_id <> ?";
            $params[] = (int)$excludeUserId;
        }

        $row = $this->db->querySingle($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    public function getSummaryByRole() {
        $sql = "SELECT
                    r.role_id,
                    r.role_name,
                    COUNT(u.user_id) AS total_users,
                    SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) AS active_users,
                    SUM(CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) AS inactive_users
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.role_id
                GROUP BY r.role_id, r.role_name
                ORDER BY r.role_id";

        return $this->db->query($sql) ?: [];
    }

    public function ensureDefaultRoles() {
        $roles = [
            ROLE_ADMIN => 'admin',
            ROLE_MECHANIC => 'mechanic',
            ROLE_FRONTDESK => 'frontdesk'
        ];

        foreach ($roles as $roleId => $roleName) {
            if (!$this->db->execute(
                "INSERT IGNORE INTO roles (role_id, role_name) VALUES (?, ?)",
                [(int)$roleId, $roleName]
            )) {
                return false;
            }
        }

        return true;
    }
}
