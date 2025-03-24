<?php
session_start();

// Check if verification code and user ID are set in the session
if (!isset($_SESSION['verification_code']) || !isset($_SESSION['user_id'])) {
    die('Invalid request. Please try again.');
}

// Get the entered code from the form
if (!isset($_POST['verification_code'])) {
    die('Verification code is required.');
}
$entered_code = $_POST['verification_code'];

// Check if the entered code matches the session's verification code
if ($entered_code == $_SESSION['verification_code']) {
    // Verification successful

    // Check if this is a "forgot password" request
    if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] == 1) {
        // Redirect to the password reset page
        $_SESSION['reset_user_id'] = $_SESSION['user_id'];
        header("Location: change_password.php");
        exit();
    } else {
        // Regular login verification
        $role = $_SESSION['role'];
        $branch = $_SESSION['branch'];
        $username = $_SESSION['username'];

        // Redirect based on role
        if ($role === 'hod') {
            header("Location: hod_dashboard.php");
        } else if ($role === 'faculty') {
            header("Location: faculty_dashboard.php");
        } else if ($role === 'principal') {
            header("Location: principal_dashboard.php");
        } else {
            header("Location: dashboard.php"); // Default dashboard for other roles
        }
        exit();
    }
} else {
    // Invalid verification code
    echo "Invalid verification code. Please try again.";
}

// Clear session variables related to verification
unset($_SESSION['verification_code']);
unset($_SESSION['forgot_password']);

// Regenerate session ID for security
session_regenerate_id(true);
?>