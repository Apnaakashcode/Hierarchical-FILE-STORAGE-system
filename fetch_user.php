<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = "Root@123"; // Replace with your database password
$dbname = "admin_panel"; // Replace with your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get the username from the request
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'];

if (empty($username)) {
    die(json_encode(['success' => false, 'message' => 'Username is required']));
}

// Fetch user details
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

if ($user_id) {
    echo json_encode(['success' => true, 'user_id' => $user_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$conn->close();
?>