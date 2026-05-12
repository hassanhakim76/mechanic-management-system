<?php
/**
 * Database Class
 * PDO-based database connection and query handling
 */

class Database {
    private $conn;
    private static $instance = null;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute a query and return all results
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($e, $sql);
            return false;
        }
    }
    
    /**
     * Execute a query and return a single row
     */
    public function querySingle($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->handleError($e, $sql);
            return false;
        }
    }
    
    /**
     * Execute an insert/update/delete query
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->handleError($e, $sql);
            return false;
        }
    }
    
    /**
     * Insert a record and return the last insert ID
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            $this->handleError($e, $sql);
            return false;
        }
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Get row count
     */
    public function rowCount($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e, $sql);
            return 0;
        }
    }
    
    /**
     * Handle database errors
     */
    private function handleError($e, $sql = '') {
        if (DEBUG_MODE) {
            echo "<div style='background: #ffdddd; border: 1px solid #ff0000; padding: 10px; margin: 10px;'>";
            echo "<strong>Database Error:</strong> " . $e->getMessage() . "<br>";
            if ($sql) {
                echo "<strong>SQL:</strong> " . $sql . "<br>";
            }
            echo "</div>";
        }
        error_log("Database Error: " . $e->getMessage() . " | SQL: " . $sql);
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
