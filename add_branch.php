<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$branchName = $input['branch'];

// Validate input
if (empty($branchName)) {
    echo json_encode(['success' => false, 'message' => 'Branch name is required']);
    exit;
}

// Check if branch already exists
$sql = "SELECT * FROM branches WHERE branch_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $branchName);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Branch already exists']);
    exit;
}

// Insert new branch
$sql = "INSERT INTO branches (branch_name) VALUES (?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $branchName);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Branch added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add branch']);
}

$stmt->close();
$conn->close();
?>