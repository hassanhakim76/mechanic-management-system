<?php
/**
 * Customer Model
 * Handles customer data operations
 */

class Customer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get customer by ID
     */
    public function getById($customerId) {
        $sql = "SELECT * FROM customers WHERE CustomerID = ?";
        return $this->db->querySingle($sql, [$customerId]);
    }
    
    /**
     * Search customer by email, phone, or cell
     */
    public function search($searchTerm) {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM customer_vehicle WHERE CustomerID = c.CustomerID AND Status = 'A') as vehicle_count
                FROM customers c
                WHERE c.Email LIKE ? 
                   OR c.Phone LIKE ? 
                   OR c.Cell LIKE ?
                ORDER BY c.CustomerID DESC
                LIMIT 10";
        
        $term = '%' . $searchTerm . '%';
        return $this->db->query($sql, [$term, $term, $term]);
    }
    
    /**
     * Advanced search
     */
    public function advancedSearch($field, $operator, $value) {
        $allowedFields = ['FirstName', 'LastName', 'Phone', 'Cell', 'Email', 'PostalCode', 'City'];
        
        if (!in_array($field, $allowedFields)) {
            return [];
        }
        
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM customer_vehicle WHERE CustomerID = c.CustomerID AND Status = 'A') as vehicle_count
                FROM customers c
                WHERE ";
        
        switch ($operator) {
            case 'Equal':
                $sql .= "$field = ?";
                $params = [$value];
                break;
            case 'Start With':
                $sql .= "$field LIKE ?";
                $params = [$value . '%'];
                break;
            case 'End With':
                $sql .= "$field LIKE ?";
                $params = ['%' . $value];
                break;
            case 'Contain':
            default:
                $sql .= "$field LIKE ?";
                $params = ['%' . $value . '%'];
                break;
        }
        
        $sql .= " ORDER BY c.CustomerID DESC LIMIT 50";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Create new customer
     */
    public function create($data) {
        // Normalize data as per VB app
        $data['FirstName'] = titleCase($data['FirstName'] ?? '');
        $data['LastName'] = titleCase($data['LastName'] ?? '');
        $data['City'] = titleCase($data['City'] ?? '');
        $data['PostalCode'] = formatPostalCode($data['PostalCode'] ?? '');
        $data['Phone'] = formatPhone($data['Phone'] ?? '');
        $data['Cell'] = formatPhone($data['Cell'] ?? '');
        $data['PhoneExt'] = !empty($data['PhoneExt']) ? (int)$data['PhoneExt'] : null;
        
        $sql = "INSERT INTO customers (
                    FirstName, LastName, Phone, Cell, Email, 
                    Address, City, Province, PostalCode, PhoneExt, subscribe
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 1, b'1', b'0'))";
        
        return $this->db->insert($sql, [
            $data['FirstName'],
            $data['LastName'],
            $data['Phone'],
            $data['Cell'],
            $data['Email'] ?? '',
            $data['Address'] ?? '',
            $data['City'],
            $data['Province'] ?? '',
            $data['PostalCode'],
            $data['PhoneExt'],
            isset($data['subscribe']) ? 1 : 0
        ]);
    }
    
    /**
     * Update customer
     */
    public function update($customerId, $data) {
        // Normalize data as per VB app
        $data['FirstName'] = titleCase($data['FirstName'] ?? '');
        $data['LastName'] = titleCase($data['LastName'] ?? '');
        $data['City'] = titleCase($data['City'] ?? '');
        $data['PostalCode'] = formatPostalCode($data['PostalCode'] ?? '');
        $data['Phone'] = formatPhone($data['Phone'] ?? '');
        $data['Cell'] = formatPhone($data['Cell'] ?? '');
        $data['PhoneExt'] = !empty($data['PhoneExt']) ? (int)$data['PhoneExt'] : null;
        
        $sql = "UPDATE customers SET
                FirstName = ?, LastName = ?, Phone = ?, Cell = ?, Email = ?,
                Address = ?, City = ?, Province = ?, PostalCode = ?, PhoneExt = ?, subscribe = IF(? = 1, b'1', b'0')
                WHERE CustomerID = ?";
        
        return $this->db->execute($sql, [
            $data['FirstName'],
            $data['LastName'],
            $data['Phone'],
            $data['Cell'],
            $data['Email'] ?? '',
            $data['Address'] ?? '',
            $data['City'],
            $data['Province'] ?? '',
            $data['PostalCode'],
            $data['PhoneExt'],
            isset($data['subscribe']) ? 1 : 0,
            $customerId
        ]);
    }
    
    /**
     * Delete customer (soft delete not applicable, but prevent if has work orders)
     */
    public function delete($customerId) {
        // Check if customer has work orders
        $woCount = $this->db->querySingle(
            "SELECT COUNT(*) as cnt FROM work_order WHERE CustomerID = ?",
            [$customerId]
        );
        
        if ($woCount['cnt'] > 0) {
            return false; // Cannot delete customer with work orders
        }
        
        $sql = "DELETE FROM customers WHERE CustomerID = ?";
        return $this->db->execute($sql, [$customerId]);
    }
    
    /**
     * Get all customers
     */
    public function getAll($limit = 100, $offset = 0) {
        $sql = "SELECT c.*,
                (SELECT COUNT(*) FROM customer_vehicle WHERE CustomerID = c.CustomerID AND Status = 'A') as vehicle_count,
                (SELECT COUNT(*) FROM work_order WHERE CustomerID = c.CustomerID) as wo_count
                FROM customers c
                ORDER BY c.CustomerID DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->query($sql, [$limit, $offset]);
    }
    
    /**
     * Get customer count
     */
    public function getCount() {
        $result = $this->db->querySingle("SELECT COUNT(*) as cnt FROM customers");
        return $result['cnt'];
    }
    
    /**
     * Get customer vehicles
     */
    public function getVehicles($customerId) {
        $sql = "SELECT * FROM customer_vehicle 
                WHERE CustomerID = ? AND Status = 'A'
                ORDER BY CVID DESC";
        
        return $this->db->query($sql, [$customerId]);
    }
    
    /**
     * Get customer work orders
     */
    public function getWorkOrders($customerId, $limit = 20) {
        $sql = "SELECT wo.*, cv.Plate, cv.Make, cv.Model, cv.Year, cv.Color
                FROM work_order wo
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE wo.CustomerID = ?
                ORDER BY wo.WOID DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$customerId, $limit]);
    }
}
