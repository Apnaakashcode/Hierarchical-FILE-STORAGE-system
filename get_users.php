<?php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', 'Root@123', 'admin_panel');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT username, branch FROM users WHERE role = 'faculty' OR role = 'office'");

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);

$conn->close();
?>