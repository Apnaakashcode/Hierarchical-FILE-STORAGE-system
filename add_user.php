<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$branch = $_POST['branch']; // Branch is dynamically updated, so it's optional
$role = $_POST['role'];

$uploads_dir = "uploads/";

// Validate form inputs
if (empty($username) || empty($email) || empty($role)) {
    die("Error: Username, email, and role are required.");
}

// Check if email already exists in the database
$email_check_sql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($email_check_sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("Error: Email already exists.");
}
$stmt->close();

// If principal, create their folder and subfolders
if ($role == "principal") {
    $principal_dir = $uploads_dir . $username . "/";
    if (!file_exists($principal_dir)) {
        if (!mkdir($principal_dir, 0777, true)) {
            die("Error: Failed to create principal folder.");
        }
        // Create HOD and MY_UPLOADS subfolders
        if (!mkdir($principal_dir . "HOD/", 0777, true)) {
            die("Error: Failed to create HOD subfolder.");
        }
        if (!mkdir($principal_dir . "MY_UPLOADS/", 0777, true)) {
            die("Error: Failed to create MY_UPLOADS subfolder.");
        }
        echo "Principal folder and subfolders created: $principal_dir <br>";
    }
}

// Check if the role is 'hod' and if a HOD already exists for the branch
if ($role == "hod") {
    // Ensure a principal exists
    $principal_sql = "SELECT username FROM users WHERE role = 'principal'";
    $principal_result = $conn->query($principal_sql);

    if ($principal_result->num_rows == 0) {
        die("Error: No principal found. Please add a principal first.");
    }

    // Check if a HOD already exists for the branch
    $check_sql = "SELECT id, username FROM users WHERE branch = ? AND role = 'hod'";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $branch);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        die("Error: Only one HOD can be added per branch.");
    }

    // Get the principal's username
    $principal_row = $principal_result->fetch_assoc();
    $principal_username = $principal_row['username'];

    // Create HOD folder inside the principal's HOD folder
    $hod_dir = $uploads_dir . $principal_username . "/HOD/" . $username . "/";
    if (!file_exists($hod_dir)) {
        if (!mkdir($hod_dir, 0777, true)) {
            die("Error: Failed to create HOD folder.");
        }
        // Create FACULTY, MY_UPLOADS, and PRINCIPAL_UPLOADS subfolders
        if (!mkdir($hod_dir . "FACULTY/", 0777, true)) {
            die("Error: Failed to create FACULTY subfolder.");
        }
        if (!mkdir($hod_dir . "MY_UPLOADS/", 0777, true)) {
            die("Error: Failed to create MY_UPLOADS subfolder.");
        }
        if (!mkdir($hod_dir . "PRINCIPAL_UPLOADS/", 0777, true)) {
            die("Error: Failed to create PRINCIPAL_UPLOADS subfolder.");
        }
        echo "HOD folder and subfolders created: $hod_dir <br>";
    }
}

// If faculty, create their folder inside the respective HOD's faculty folder
if ($role == "faculty") {
    // Ensure a principal exists
    $principal_sql = "SELECT username FROM users WHERE role = 'principal'";
    $principal_result = $conn->query($principal_sql);

    if ($principal_result->num_rows == 0) {
        die("Error: No principal found. Please add a principal first.");
    }

    // Get the principal's username
    $principal_row = $principal_result->fetch_assoc();
    $principal_username = $principal_row['username'];

    // Ensure a HOD exists for the branch
    $hod_sql = "SELECT username FROM users WHERE branch = ? AND role = 'hod'";
    $stmt = $conn->prepare($hod_sql);
    $stmt->bind_param("s", $branch);
    $stmt->execute();
    $hod_result = $stmt->get_result();

    if ($hod_result->num_rows == 0) {
        die("Error: No HOD found for the branch $branch.");
    }

    // Get the HOD's username
    $hod_row = $hod_result->fetch_assoc();
    $hod_username = $hod_row['username'];

    // Create faculty folder inside the HOD's faculty folder
    $faculty_dir = $uploads_dir . $principal_username . "/HOD/" . $hod_username . "/FACULTY/" . $username . "/";
    if (!file_exists($faculty_dir)) {
        if (!mkdir($faculty_dir, 0777, true)) {
            die("Error: Failed to create faculty folder.");
        }
        // Create MY_UPLOADS and HOD_UPLOADS subfolders
        if (!mkdir($faculty_dir . "MY_UPLOADS/", 0777, true)) {
            die("Error: Failed to create MY_UPLOADS subfolder.");
        }
        if (!mkdir($faculty_dir . "HOD_UPLOADS/", 0777, true)) {
            die("Error: Failed to create HOD_UPLOADS subfolder.");
        }
        echo "Faculty folder and subfolders created: $faculty_dir <br>";
    }
}

// Insert user into database
$sql = "INSERT INTO users (username, email, password, branch, role) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $username, $email, $password, $branch, $role);

if ($stmt->execute()) {
    echo "New user created successfully.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>