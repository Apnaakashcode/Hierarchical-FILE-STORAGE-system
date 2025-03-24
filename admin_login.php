<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'];
$password = $data['password'];

// Hardcoded admin credentials
$adminUsername = "admin";
$adminPassword = "ideal123";

// Check if the provided credentials match the admin credentials
if ($username === $adminUsername && $password === $adminPassword) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>