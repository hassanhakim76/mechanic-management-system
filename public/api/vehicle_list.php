<?php
/**
 * Vehicle List API
 * Returns active vehicles for a specific customer
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$customerId = $_GET['customer_id'] ?? 0;

if (!$customerId) {
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

$db = Database::getInstance();

try {
    $sql = "
        SELECT CVID, CustomerID, Plate, VIN, Make, Model, Year, Color, Engine, Detail
        FROM customer_vehicle 
        WHERE CustomerID = ? AND Status = 'A'
        ORDER BY Year DESC, Make, Model
    ";
    
    $results = $db->query($sql, [$customerId]);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('[vehicle_list] ' . $e->getMessage());
    echo json_encode(['error' => 'Vehicle list is temporarily unavailable.']);
}
