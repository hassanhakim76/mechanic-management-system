<?php
/**
 * LetterTemplate Model
 * Management of email/letter templates
 */

class LetterTemplate {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll() {
        return $this->db->query("SELECT * FROM letter_template ORDER BY name");
    }
    
    public function getById($id) {
        return $this->db->querySingle("SELECT * FROM letter_template WHERE tid = ?", [$id]);
    }
    
    public function create($data) {
        $sql = "INSERT INTO letter_template (name, type, subject, content, status) VALUES (:name, :type, :subject, :content, :status)";
        
        $params = [
            ':name' => $data['name'],
            ':type' => $data['type'], // 'Email' or 'Letter'
            ':subject' => $data['subject'],
            ':content' => $data['content'],
            ':status' => $data['status'] ?? 'A'
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    public function update($id, $data) {
        $sql = "UPDATE letter_template SET name = :name, type = :type, subject = :subject, content = :content, status = :status WHERE tid = :id";
        
        $params = [
            ':id' => $id,
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':subject' => $data['subject'],
            ':content' => $data['content'],
            ':status' => $data['status']
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function delete($id) {
        return $this->db->execute("DELETE FROM letter_template WHERE tid = ?", [$id]);
    }
}
