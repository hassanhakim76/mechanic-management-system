<?php
/**
 * Vehicle Model
 * Handles customer vehicle data operations
 */

class Vehicle {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get vehicle by ID
     */
    public function getById($cvid) {
        $sql = "SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell, c.Email
                FROM customer_vehicle cv
                JOIN customers c ON cv.CustomerID = c.CustomerID
                WHERE cv.CVID = ?";
        
        return $this->db->querySingle($sql, [$cvid]);
    }
    
    /**
     * Search vehicle by plate or VIN
     */
    public function search($searchTerm) {
        $sql = "SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell, c.Email
                FROM customer_vehicle cv
                JOIN customers c ON cv.CustomerID = c.CustomerID
                WHERE cv.Status = 'A'
                  AND (cv.Plate LIKE ? OR cv.VIN LIKE ?)
                ORDER BY cv.CVID DESC
                LIMIT 10";
        
        $term = '%' . $searchTerm . '%';
        return $this->db->query($sql, [$term, $term]);
    }
    
    /**
     * Search by exact plate
     */
    public function searchByPlate($plate) {
        $sql = "SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell, c.Email
                FROM customer_vehicle cv
                JOIN customers c ON cv.CustomerID = c.CustomerID
                WHERE cv.Plate = ? AND cv.Status = 'A'
                LIMIT 1";
        
        return $this->db->querySingle($sql, [$plate]);
    }
    
    /**
     * Search by exact VIN
     */
    public function searchByVIN($vin) {
        $sql = "SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell, c.Email
                FROM customer_vehicle cv
                JOIN customers c ON cv.CustomerID = c.CustomerID
                WHERE cv.VIN = ? AND cv.Status = 'A'
                LIMIT 1";
        
        return $this->db->querySingle($sql, [$vin]);
    }
    
    /**
     * Create new vehicle
     */
    public function create($data) {
        $sql = "INSERT INTO customer_vehicle (
                    CustomerID, Plate, VIN, Make, Model, Year, Color, Status, Engine, Detail
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->db->insert($sql, [
            $data['CustomerID'],
            strtoupper($data['Plate'] ?? ''),
            strtoupper($data['VIN'] ?? ''),
            $data['Make'] ?? '',
            $data['Model'] ?? '',
            $data['Year'] ?? '',
            $data['Color'] ?? '',
            VEHICLE_ACTIVE,
            $data['Engine'] ?? '',
            $data['Detail'] ?? ''
        ]);
    }
    
    /**
     * Update vehicle
     */
    public function update($cvid, $data) {
        $sql = "UPDATE customer_vehicle SET
                CustomerID = ?, Plate = ?, VIN = ?, Make = ?, Model = ?, 
                Year = ?, Color = ?, Engine = ?, Detail = ?
                WHERE CVID = ?";
        
        return $this->db->execute($sql, [
            $data['CustomerID'],
            strtoupper($data['Plate'] ?? ''),
            strtoupper($data['VIN'] ?? ''),
            $data['Make'] ?? '',
            $data['Model'] ?? '',
            $data['Year'] ?? '',
            $data['Color'] ?? '',
            $data['Engine'] ?? '',
            $data['Detail'] ?? '',
            $cvid
        ]);
    }
    
    /**
     * Soft delete vehicle (set Status to 'I')
     */
    public function delete($cvid) {
        $sql = "UPDATE customer_vehicle SET Status = ? WHERE CVID = ?";
        return $this->db->execute($sql, [VEHICLE_INACTIVE, $cvid]);
    }
    
    /**
     * Activate vehicle
     */
    public function activate($cvid) {
        $sql = "UPDATE customer_vehicle SET Status = ? WHERE CVID = ?";
        return $this->db->execute($sql, [VEHICLE_ACTIVE, $cvid]);
    }
    
    /**
     * Get vehicles by customer
     */
    public function getByCustomer($customerId, $activeOnly = true) {
        $sql = "SELECT * FROM customer_vehicle WHERE CustomerID = ?";
        $params = [$customerId];
        
        if ($activeOnly) {
            $sql .= " AND Status = ?";
            $params[] = VEHICLE_ACTIVE;
        }
        
        $sql .= " ORDER BY CVID DESC";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get vehicle work orders
     */
    public function getWorkOrders($cvid, $limit = 20) {
        $sql = "SELECT wo.*, c.FirstName, c.LastName, c.Phone, c.Cell
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                WHERE wo.CVID = ?
                ORDER BY wo.WOID DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$cvid, $limit]);
    }
    
    /**
     * Get all vehicles
     */
    public function getAll($activeOnly = true, $limit = 100, $offset = 0) {
        $sql = "SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell,
                (SELECT COUNT(*) FROM work_order WHERE CVID = cv.CVID) as wo_count
                FROM customer_vehicle cv
                JOIN customers c ON cv.CustomerID = c.CustomerID";
        
        $params = [];
        
        if ($activeOnly) {
            $sql .= " WHERE cv.Status = ?";
            $params[] = VEHICLE_ACTIVE;
        }
        
        $sql .= " ORDER BY cv.CVID DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get vehicle count
     */
    public function getCount($activeOnly = true) {
        $sql = "SELECT COUNT(*) as cnt FROM customer_vehicle";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " WHERE Status = ?";
            $params[] = VEHICLE_ACTIVE;
        }
        
        $result = $this->db->querySingle($sql, $params);
        return $result['cnt'];
    }
}
