<?php
$host = 'localhost'; // Database host
$dbname = 'admin_panel'; // Database name
$username = 'root'; // Database username
$password = 'Root@123'; // Database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>