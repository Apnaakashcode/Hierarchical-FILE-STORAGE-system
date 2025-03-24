<?php
session_start();

if (!isset($_GET['user_id'])) {
    die('Invalid request');
}

$user_id = $_GET['user_id'];
$forgot_password = isset($_GET['forgot_password']) ? $_GET['forgot_password'] : 0;

// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = "Root@123"; // Replace with your database password
$dbname = "admin_panel"; // Replace with your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die('Database connection failed');
}

// Fetch user details (email, role, branch, username)
$stmt = $conn->prepare("SELECT email, role, branch, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email, $role, $branch, $username);
$stmt->fetch();
$stmt->close();

// Generate a random verification code
$verification_code = rand(100000, 999999);
$_SESSION['verification_code'] = $verification_code;
$_SESSION['user_id'] = $user_id;
$_SESSION['role'] = $role;
$_SESSION['branch'] = $branch;
$_SESSION['username'] = $username;
$_SESSION['forgot_password'] = $forgot_password; // Store forgot_password flag in session

// Send email using PHPMailer
require 'vendor/autoload.php'; // Include PHPMailer

$mail = new PHPMailer\PHPMailer\PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
$mail->SMTPAuth = true;
$mail->Username = 'akashdevisetti64@gmail.com'; // Replace with your email
$mail->Password = 'dbhj hucr qsua mehq'; // Replace with your email password
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('akashdevisetti64@gmail.com', 'IDEAL HUB');
$mail->addAddress($email);
$mail->Subject = 'Email Verification Code';
$mail->Body = "Your verification code is: $verification_code";

if ($mail->send()) {
    $message = "A verification code has been sent to your email.";
} else {
    $message = "Failed to send verification code.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
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
        .verification-container {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }
        .verification-container input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: rgba(255, 255, 255, 0.1); /* Semi-transparent input background */
            color: #fff;
        }
        .verification-container h2,p {
            color:white;
        }

        .verification-container button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .verification-container button:hover {
            background-color : green;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h2>Email Verification</h2>
        <p><?php echo $message; ?></p>
        <form action="verify_code.php" method="POST">
            <input type="text" name="verification_code" placeholder="Enter verification code" required>
            <button type="submit">Verify</button>
        </form>
    </div>
</body>
</html>