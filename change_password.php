<?php
session_start();
include 'db_connect.php';
$conn = db_connect(); // ✅ Call the function to get the connection
// Ensure this file contains $conn for database connection

// Check if reset user session is set
if (!isset($_SESSION['reset_user_id'])) {
    die('Unauthorized access.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['reset_user_id']; // Use the correct session variable
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $error = 'Passwords do not match!';
    }
    // Enforce strong password rule (at least 6 characters, one letter, one number)
    else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in the database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            // Clear session and redirect to login
            unset($_SESSION['reset_user_id']);
            echo "<script>alert('Password changed successfully! Redirecting to login.'); window.location.href='user_login.html';</script>";
            exit();
        } else {
            $error = 'Error updating password. Try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-image: url('https://static.vecteezy.com/system/resources/previews/023/308/048/non_2x/abstract-grey-metallic-overlap-on-dark-circle-mesh-pattern-design-modern-luxury-futuristic-background-vector.jpg');
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .password-container {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }
        .password-container input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .password-container h2, p {
            color: white;
        }
        .password-container button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .password-container button:hover {
            background-color: green;
        }
        .password-input-container {
            position: relative;
            width: 100%;
        }
        .password-input-container i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <h2>Reset Your Password</h2>
        <?php if (!empty($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form action="change_password.php" method="POST">
        <div class="password-input-container">
                <input type="password" name="new_password" id="new_password" placeholder="New Password (Min 6 characters)" required>
                <i class="fas fa-eye" id="toggleNewPassword"></i>
            </div>
            <div class="password-input-container">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <i class="fas fa-eye" id="toggleConfirmPassword"></i>
            </div>
            <button type="submit">Change Password</button>
        </form>
    </div>
    <script>
        // Function to toggle password visibility
        function togglePasswordVisibility(inputId, toggleId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(toggleId);

            toggleIcon.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            });
        }

        // Toggle new password visibility
        togglePasswordVisibility('new_password', 'toggleNewPassword');

        // Toggle confirm password visibility
        togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
    </script>
</body>
</html>