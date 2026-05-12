<?php
/**
 * Customer Search API
 * Searches for customers by Phone, Email, or Vehicle Plate
 * Logic matches VB app: Returns best match
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 3) {
    echo json_encode([]); // Too short to search
    exit;
}

$db = Database::getInstance();

try {
    $params = [];
    $term = '%' . $query . '%';
    $sql = "
        SELECT DISTINCT
            c.CustomerID,
            c.FirstName,
            c.LastName,
            c.Phone,
            c.Cell,
            c.Email
        FROM customers c
        LEFT JOIN customer_vehicle cv
            ON c.CustomerID = cv.CustomerID
           AND cv.Status = 'A'
        WHERE c.FirstName LIKE ?
           OR c.LastName LIKE ?
           OR c.Phone LIKE ?
           OR c.Cell LIKE ?
           OR c.Email LIKE ?
           OR cv.Plate LIKE ?
           OR cv.VIN LIKE ?
        ORDER BY c.CustomerID DESC
        LIMIT 10
    ";
    $params = [$term, $term, $term, $term, $term, $term, $term];

    $results = $db->query($sql, $params);

    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('[customer_search] ' . $e->getMessage());
    echo json_encode(['error' => 'Customer search is temporarily unavailable.']);
}
