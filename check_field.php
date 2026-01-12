<?php
require_once 'config/config.php';
require_once 'config/db.php';

$res = $conn->query("SELECT * FROM form_fields WHERE field_name = 'college'");
if ($row = $res->fetch_assoc()) {
    echo "<h1>Field: " . $row['field_label'] . "</h1>";
    echo "<p>Type: " . $row['field_type'] . "</p>";
    echo "<p>Options: " . htmlspecialchars($row['field_options']) . "</p>";
} else {
    echo "Field 'college' not found.";
    // Check all fields
    $all = $conn->query("SELECT field_name FROM form_fields");
    echo "<hr>Available fields: ";
    while($r = $all->fetch_assoc()) { echo $r['field_name'] . ", "; }
}
