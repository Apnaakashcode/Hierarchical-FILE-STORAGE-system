<?php
function db_connect() {
    $conn = new mysqli('localhost', 'root', 'Root@123', 'admin_panel');

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>
