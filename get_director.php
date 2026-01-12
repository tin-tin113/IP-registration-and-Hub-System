<?php
require_once 'config/config.php';
require_once 'config/db.php';
$res = $conn->query("SELECT email FROM users WHERE role='director' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "Director Email: " . $row['email'];
} else {
    echo "No director found. Creating one...";
    $pass = password_hash('password123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (email, password, full_name, role) VALUES ('director@chmsu.edu.ph', '$pass', 'Director Test', 'director')");
    echo "Created director@chmsu.edu.ph / password123";
}
