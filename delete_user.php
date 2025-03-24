<?php
header('Content-Type: application/json'); // Ensure JSON response

// Database connection
$servername = "localhost";
$username = "root";
$password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Get input data
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST; // Fallback for form submissions
}

$username = $data['username'];

if (empty($username)) {
    echo json_encode(["success" => false, "message" => "Username is required"]);
    exit;
}

// Fetch user details before deletion (optional, for logging or validation)
$sql = "SELECT branch, role FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

// Delete user from database
$sql = "DELETE FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User deleted successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error deleting user: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>