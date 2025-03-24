<?php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', 'Root@123', 'admin_panel');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT files.file_name, users.username, users.branch FROM files JOIN users ON files.user_id = users.id");

$files = [];
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}

echo json_encode($files);

$conn->close();
?>