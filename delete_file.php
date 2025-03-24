<?php
session_start();

// Check if the user is logged in and is the principal or HOD
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'principal' && $_SESSION['role'] !== 'hod')) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$uploads_folder = "uploads/$username/";

// Get folder and file details from the query parameters
$hod_folder = isset($_GET['hod_folder']) ? $_GET['hod_folder'] : null;
$faculty_folder = isset($_GET['faculty_folder']) ? $_GET['faculty_folder'] : null;
$file_name = isset($_GET['file']) ? $_GET['file'] : null;
$folder_name = isset($_GET['folder']) ? $_GET['folder'] : null;
$current_folder = isset($_GET['current_folder']) ? $_GET['current_folder'] : null; // New parameter

// Determine the file or folder path based on the type of deletion
if ($hod_folder && $faculty_folder && $file_name) {
    // Delete a file in a faculty folder (HOD dashboard)
    $file_path = $uploads_folder . $hod_folder . '/' . $faculty_folder . '/' . $file_name;
} elseif ($hod_folder && $file_name) {
    // Delete a file in an HOD folder (HOD dashboard)
    $file_path = $uploads_folder . $hod_folder . '/' . $file_name;
} elseif ($file_name) {
    // Delete a file in the principal's folder (Principal dashboard)
    $file_path = $uploads_folder . $file_name;
} elseif ($folder_name) {
    // Delete a folder (recursively) - works for both dashboards
    $folder_path = $uploads_folder . $folder_name;
} else {
    echo "Invalid request.";
    exit();
}

// Handle file deletion
if (isset($file_path) && file_exists($file_path)) {
    if (unlink($file_path)) {
        $message = "File deleted successfully.";
    } else {
        $message = "Failed to delete file.";
    }
}
// Handle folder deletion
elseif (isset($folder_path) && is_dir($folder_path)) {
    // Recursively delete the folder and its contents
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $action = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $action($fileinfo->getRealPath());
    }

    if (rmdir($folder_path)) {
        $message = "Folder deleted successfully.";
    } else {
        $message = "Failed to delete folder.";
    }
} else {
    $message = "File or folder not found.";
}

// Redirect back to the appropriate dashboard
if ($hod_folder && $faculty_folder) {
    // Redirect to the faculty folder in HOD dashboard
    header("Location: hod_dashboard.php?hod_folder=$hod_folder&faculty_folder=$faculty_folder&message=" . urlencode($message));
} elseif ($hod_folder) {
    // Redirect to the HOD folder in HOD dashboard
    header("Location: hod_dashboard.php?hod_folder=$hod_folder&message=" . urlencode($message));
} else {
    // Redirect to the principal's dashboard with the current folder
    $redirect_url = "principal_dashboard.php?message=" . urlencode($message);
    if ($current_folder) {
        $redirect_url .= "&folder=" . urlencode($current_folder);
    }
    header("Location: $redirect_url");
}
exit();
?>