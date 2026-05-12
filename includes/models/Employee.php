<?php
/**
 * Employee Model
 * Handles employee/mechanic data operations
 */

class Employee {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get employee by ID
     */
    public function getById($employeeId) {
        $sql = "SELECT * FROM employees WHERE EmployeeID = ?";
        return $this->db->querySingle($sql, [$employeeId]);
    }
    
    /**
     * Get all active mechanics for dropdown
     */
    public function getMechanics() {
        $sql = "SELECT EmployeeID, FirstName, LastName, Display, Position
                FROM employees
                WHERE Position LIKE '%mechanic%' AND Status = ?
                ORDER BY Display, LastName, FirstName";
        
        return $this->db->query($sql, [EMPLOYEE_ACTIVE]);
    }
    
    /**
     * Get all active employees
     */
    public function getActive() {
        $sql = "SELECT * FROM employees WHERE Status = ? ORDER BY LastName, FirstName";
        return $this->db->query($sql, [EMPLOYEE_ACTIVE]);
    }
    
    /**
     * Get all employees
     */
    public function getAll() {
        $sql = "SELECT *
                FROM employees
                ORDER BY
                    CASE WHEN Status = ? THEN 0 ELSE 1 END,
                    LastName,
                    FirstName,
                    Display";
        return $this->db->query($sql, [EMPLOYEE_ACTIVE]);
    }
    
    /**
     * Create new employee
     */
    public function create($data) {
        $sql = "INSERT INTO employees (
                    FirstName, LastName, Display, Phone, Cell, Email,
                    Address, City, Province, PostalCode, Position, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $display = !empty($data['Display']) ? $data['Display'] : trim($data['FirstName'] . ' ' . $data['LastName']);
        
        return $this->db->insert($sql, [
            titleCase($data['FirstName'] ?? ''),
            titleCase($data['LastName'] ?? ''),
            $display,
            formatPhone($data['Phone'] ?? ''),
            formatPhone($data['Cell'] ?? ''),
            $data['Email'] ?? '',
            $data['Address'] ?? '',
            titleCase($data['City'] ?? ''),
            $data['Province'] ?? '',
            formatPostalCode($data['PostalCode'] ?? ''),
            $data['Position'] ?? '',
            $data['Status'] ?? EMPLOYEE_ACTIVE
        ]);
    }
    
    /**
     * Update employee
     */
    public function update($employeeId, $data) {
        $sql = "UPDATE employees SET
                FirstName = ?, LastName = ?, Display = ?, Phone = ?, Cell = ?, Email = ?,
                Address = ?, City = ?, Province = ?, PostalCode = ?, Position = ?, Status = ?
                WHERE EmployeeID = ?";
        
        $display = !empty($data['Display']) ? $data['Display'] : trim($data['FirstName'] . ' ' . $data['LastName']);
        
        return $this->db->execute($sql, [
            titleCase($data['FirstName'] ?? ''),
            titleCase($data['LastName'] ?? ''),
            $display,
            formatPhone($data['Phone'] ?? ''),
            formatPhone($data['Cell'] ?? ''),
            $data['Email'] ?? '',
            $data['Address'] ?? '',
            titleCase($data['City'] ?? ''),
            $data['Province'] ?? '',
            formatPostalCode($data['PostalCode'] ?? ''),
            $data['Position'] ?? '',
            $data['Status'] ?? EMPLOYEE_ACTIVE,
            $employeeId
        ]);
    }
    
    /**
     * Soft delete employee (set Status to 'I')
     */
    public function delete($employeeId) {
        $sql = "UPDATE employees SET Status = ? WHERE EmployeeID = ?";
        return $this->db->execute($sql, [EMPLOYEE_INACTIVE, $employeeId]);
    }
    
    /**
     * Activate employee
     */
    public function activate($employeeId) {
        $sql = "UPDATE employees SET Status = ? WHERE EmployeeID = ?";
        return $this->db->execute($sql, [EMPLOYEE_ACTIVE, $employeeId]);
    }
    
    /**
     * Search employees
     */
    public function search($searchTerm) {
        $sql = "SELECT * FROM employees
                WHERE FirstName LIKE ? OR LastName LIKE ? OR Display LIKE ? OR Phone LIKE ? OR Cell LIKE ?
                ORDER BY LastName, FirstName
                LIMIT 20";
        
        $term = '%' . $searchTerm . '%';
        return $this->db->query($sql, [$term, $term, $term, $term, $term]);
    }
}
