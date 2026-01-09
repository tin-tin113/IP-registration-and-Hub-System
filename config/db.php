<?php
// Database Connection

require_once 'config.php';

class Database {
  private $connection;
  
  public function __construct() {
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($this->connection->connect_error) {
      die("Database connection failed: " . $this->connection->connect_error);
    }
    
    $this->connection->set_charset("utf8mb4");
  }
  
  public function getConnection() {
    return $this->connection;
  }
  
  public function close() {
    if ($this->connection) {
      $this->connection->close();
    }
  }
}

// Create connection instance
$db = new Database();
$conn = $db->getConnection();
