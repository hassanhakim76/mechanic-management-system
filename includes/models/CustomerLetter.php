<?php
/**
 * CustomerLetter Model
 * Records history of correspondence with customers
 */

class CustomerLetter {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getByCustomerId($customerId) {
        $sql = "
            SELECT cl.*, lt.name as template_name, lt.subject
            FROM customer_letter cl
            JOIN letter_template lt ON cl.tid = lt.tid
            WHERE cl.customerid = ?
            ORDER BY cl.sentdate DESC
        ";
        return $this->db->query($sql, [$customerId]);
    }
    
    public function logLetter($customerId, $templateId) {
        $sql = "INSERT INTO customer_letter (customerid, tid, sentdate) VALUES (?, ?, NOW())";
        return $this->db->insert($sql, [$customerId, $templateId]);
    }
}
