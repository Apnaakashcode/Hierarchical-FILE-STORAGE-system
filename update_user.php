<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Include database connection
include 'db.php';

// Decode JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Log received data for debugging
error_log("Received data: " . print_r($data, true));

// Validate input data
if (!isset($data['username'], $data['email'], $data['branch'], $data['role'])) {
    echo json_encode(['message' => 'Invalid request: Missing required fields']);
    exit;
}

$username = $data['username'];
$email = $data['email'];
$branch = $data['branch'];
$role = $data['role'];
$password = isset($data['password']) && !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;

try {
    // Check if the username exists
    $checkStmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->rowCount() == 0) {
        echo json_encode(['message' => 'Error: Username not found']);
        exit;
    }

    // Prepare the update query
    if ($password) {
        $sql = "UPDATE users SET email = ?, password = ?, branch = ?, role = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email, $password, $branch, $role, $username]);
    } else {
        $sql = "UPDATE users SET email = ?, branch = ?, role = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email, $branch, $role, $username]);
    }

    // Log the SQL query for debugging
    error_log("Executing query: " . $sql);

    // Check if the update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode(['message' => 'User updated successfully']);
    } else {
        echo json_encode(['message' => 'No changes made. Affected rows: ' . $stmt->rowCount()]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}
?>