<?php
require_once '../config/config.php';
require_once '../config/db.php';

// Create application_notes table
$sql = "CREATE TABLE IF NOT EXISTS application_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES ip_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table application_notes created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
