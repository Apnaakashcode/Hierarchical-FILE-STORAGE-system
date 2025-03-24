<?php
// get_users.php

// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = "Root@123"; // Replace with your database password
$dbname = "admin_panel"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch users from the database
$sql = "SELECT username, email, branch, role FROM users"; // Replace 'users' with your table name
$result = $conn->query($sql);

$users = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['users' => $users]);

$conn->close();
?>