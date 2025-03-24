<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Fetch all branches
$sql = "SELECT branch_name FROM branches";
$result = $conn->query($sql);

if ($result === false) {
    die(json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]));
}

if ($result->num_rows > 0) {
    $branches = [];
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row['branch_name'];
    }
    echo json_encode(['success' => true, 'branches' => $branches]);
} else {
    echo json_encode(['success' => false, 'message' => 'No branches found']);
}

$conn->close();
?>