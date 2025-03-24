<?php
// logout.php

session_start();
$_SESSION = array();
session_destroy();

// Redirect to the frontend login page
header("Location: user_login.html"); // Change this line
exit();
?>