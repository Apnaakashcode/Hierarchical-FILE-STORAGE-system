<?php
session_start();
$_SESSION['notifications'] = $_SESSION['notifications'] ?? [];
// Check if the user is logged in and is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php");
    exit();
}


// Handle removal of a single notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_notification'])) {
    $index = $_POST['index'];
    if (isset($_SESSION['notifications'][$index])) {
        unset($_SESSION['notifications'][$index]);
        $_SESSION['notifications'] = array_values($_SESSION['notifications']); // Re-index array
    }
    exit();
}

// Handle clearing all notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_notifications'])) {
    $_SESSION['notifications'] = [];
    exit();
}
// Function to normalize paths
function normalizePath($path) {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('/\/+/', '/', $path);
    $path = rtrim($path, '/');
    return $path;
}

$branch = $_SESSION['branch'];
$username = $_SESSION['username'];

$servername = "localhost";
$db_username = "root";
$db_password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create the faculty folder if it doesn't exist
$principal_sql = "SELECT username FROM users WHERE role = 'principal'";
$principal_result = $conn->query($principal_sql);

if ($principal_result->num_rows > 0) {
    $principal_row = $principal_result->fetch_assoc();
    $principal_username = $principal_row['username'];

    // Find the HOD for the branch
    $hod_sql = "SELECT username FROM users WHERE branch = '$branch' AND role = 'hod'";
    $hod_result = $conn->query($hod_sql);

    if ($hod_result->num_rows > 0) {
        $hod_row = $hod_result->fetch_assoc();
        $hod_username = $hod_row['username'];

        // Faculty folder should be inside the correct hierarchy
        $faculty_folder = normalizePath("uploads/$principal_username/HOD/$hod_username/FACULTY/$username/");

        // Create the faculty folder if it doesn't exist
        if (!is_dir($faculty_folder)) {
            if (!mkdir($faculty_folder, 0777, true)) {
                die("Failed to create faculty folder.");
            }
        }

        // Handle folder navigation
        $current_folder = $faculty_folder; // Start with the faculty's root folder
        if (isset($_GET['folder'])) {
            $folder_name = $_GET['folder']; // Get the folder path from the URL
            $current_folder = normalizePath($faculty_folder . '/' . $folder_name);

            // Ensure the folder exists
            if (!is_dir($current_folder)) {
                die("Error: Folder does not exist.");
            }
        }
       
        function listFoldersRecursively($dir, $base_dir) {
            $folders = [];
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $item_path = $dir . '/' . $item;
                if (is_dir($item_path)) {
                    // Get the relative path
                    $relative_path = substr($item_path, strlen($base_dir) + 1);
                    $folders[] = $relative_path;
                    // Recursively list subfolders
                    $folders = array_merge($folders, listFoldersRecursively($item_path, $base_dir));
                }
            }
            return $folders;
        }

        // Fetch list of files and folders in the current directory
        $files = [];
        $folders = [];
        if (is_dir($current_folder)) {
            $items = array_diff(scandir($current_folder), ['.', '..']);
            foreach ($items as $item) {
                $item_path = $current_folder . '/' . $item;
                if (is_dir($item_path)) {
                    $folders[] = $item; // Add only directories
                } elseif (is_file($item_path)) {
                    $files[] = $item; // Add only files
                }
            }
        }

        // Handle file upload
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $selected_folder = $_POST['upload_folder']; // Relative path
            $selected_folder = normalizePath($selected_folder);
            $upload_folder = normalizePath($faculty_folder . '/' . $selected_folder);

            if (!is_dir($upload_folder)) {
                die("Invalid upload folder: $upload_folder");
            }

            if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['file']['name']);
                $file_path = normalizePath($upload_folder . '/' . $file_name);

                if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                    $message = "File uploaded successfully to $selected_folder.";

                     // Add notification with date, time, and folder path
            $current_time = date("Y-m-d H:i:s");
            $folder_type = strpos($upload_folder, 'my_uploads') !== false ? 'my_uploads' : 'hod_uploads';
            $notification = "• [$current_time] File '$file_name' uploaded to '$selected_folder'.";
            $_SESSION['notifications'][] = $notification;
                } else {
                    $message = "Failed to upload file.";
                }
            } else {
                $message = "No file selected or upload error.";
            }
        }

        // Handle main folder creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_main_folder'])) {
            $folder_name = trim($_POST['new_folder_name'] ?? $_POST['main_folder_name']);

            if (!empty($folder_name)) {
                $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);

                if (!empty($folder_name)) {
                    $new_folder_path = normalizePath($faculty_folder . '/' . $folder_name);

                    if (!is_dir($new_folder_path)) {
                        if (!mkdir($new_folder_path, 0777, true)) {
                            $error = error_get_last();
                            $message = "Failed to create folder: " . $error['message'];
                        } else {
                            $message = "Folder created successfully.";

                             // Add notification with date, time, and folder path
                    $current_time = date("Y-m-d H:i:s");
                    $folder_type = strpos($new_folder_path, 'my_uploads') !== false ? 'my_uploads' : 'hod_uploads';
                    $notification = "• [$current_time] Folder '$folder_name' created as main folder in intial page.";
                    $_SESSION['notifications'][] = $notification;
                        }
                    } else {
                        $message = "Folder already exists.";
                    }
                } else {
                    $message = "Invalid folder name. Use only letters, numbers, underscores, or hyphens.";
                }
            } else {
                $message = "Folder name cannot be empty.";
            }
             
        }

        // Handle subfolder creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sub_folder'])) {
            $parent_folder = $_POST['parent_folder']; // Selected parent folder
            $folder_name = trim($_POST['sub_folder_name']);

            if (!empty($folder_name)) {
                $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);

                if (!empty($folder_name)) {
                    $new_folder_path = normalizePath($current_folder . '/' . $parent_folder . '/' . $folder_name);

                    if (is_dir($current_folder . '/' . $parent_folder)) {
                        if (!is_dir($new_folder_path)) {
                            if (!mkdir($new_folder_path, 0777, true)) {
                                $error = error_get_last();
                                $message = "Failed to create folder: " . $error['message'];
                            } else {
                                $message = "Subfolder created successfully.";

                              
                        // Calculate the relative path from the faculty's root folder
                        $relative_path = substr($new_folder_path, strlen($faculty_folder) + 1);

                        // Determine the folder type
                        $folder_type = strpos($new_folder_path, 'my_uploads') !== false ? 'my_uploads' : 'hod_uploads';

                        // Add notification with date, time, and full path
                        $current_time = date("Y-m-d H:i:s");
                        $notification = "• [$current_time] Folder '$folder_name' created at path '$relative_path'.";
                        $_SESSION['notifications'][] = $notification;
                            }
                        } else {
                            $message = "Folder already exists.";
                        }
                    } else {
                        $message = "Parent folder does not exist.";
                    }
                } else {
                    $message = "Invalid folder name. Use only letters, numbers, underscores, or hyphens.";
                }
            } else {
                $message = "Folder name cannot be empty.";
            }
        }
    } else {
        die("Error: No HOD found for the branch $branch.");
    }
} else {
    die("Error: No principal found. Please add a principal first.");
}

if (isset($_GET['delete'])) {
    $file_to_delete = basename($_GET['delete']);
    $file_path = $current_folder . '/' . $file_to_delete;

    if (file_exists($file_path) && is_file($file_path)) {
        unlink($file_path);
        header("Location: faculty_dashboard.php?folder=" . ($_GET['folder'] ?? ''));
        exit();
    } else {
        echo "<script>alert('Error: File not found!');</script>";
    }
}

if (isset($_GET['delete_folder'])) {
    function deleteFolder($folder_path) {
        if (!is_dir($folder_path)) return false;
        foreach (scandir($folder_path) as $item) {
            if ($item === '.' || $item === '..') continue;
            $item_path = $folder_path . DIRECTORY_SEPARATOR . $item;
            is_dir($item_path) ? deleteFolder($item_path) : unlink($item_path);
        }
        return rmdir($folder_path);
    }

    $folder_to_delete = $current_folder . '/' . basename($_GET['delete_folder']);
    if (deleteFolder($folder_to_delete)) {
        header("Location: faculty_dashboard.php?folder=" . ($_GET['folder'] ?? ''));
        exit();
    } else {
        echo "<script>alert('Error: Could not delete folder!');</script>";
    }
}
if (isset($_GET['get_paths'])) {
    $all_paths = getAllPaths($faculty_folder, $faculty_folder);
    header('Content-Type: application/json'); // Ensure the response is JSON
    echo json_encode(['paths' => $all_paths]);
    exit();
}
// Function to get all folders recursively with their full paths
// Function to get all paths (folders and files) recursively
function getAllPaths($dir, $base_dir) {
    $paths = [];
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $item_path = $dir . '/' . $item;
        $relative_path = substr($item_path, strlen($base_dir) + 1);
        if (is_dir($item_path)) {
            $paths[] = $relative_path;
            $paths = array_merge($paths, getAllPaths($item_path, $base_dir));
        } elseif (is_file($item_path)) {
            $paths[] = $relative_path;
        }
    }
    return $paths;
}

// Get all paths for the destination dropdown
$all_paths = getAllPaths($faculty_folder, $faculty_folder);
function moveFolder($source, $destination) {
    if (!is_dir($source)) {
        return false;
    }

    // Create the destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    // Move all files and subdirectories
    $items = array_diff(scandir($source), ['.', '..']);
    foreach ($items as $item) {
        $source_item = $source . '/' . $item;
        $destination_item = $destination . '/' . $item;

        if (is_dir($source_item)) {
            moveFolder($source_item, $destination_item); // Recursively move subdirectories
        } else {
            rename($source_item, $destination_item); // Move files
        }
    }

    // Remove the source directory after moving its contents
    return rmdir($source);
}
// Handle AJAX request for getting all paths
if (isset($_GET['action']) && $_GET['action'] === 'get_all_paths') {
    $all_paths = getAllPaths($faculty_folder, $faculty_folder);
    foreach ($all_paths as $path) {
        echo "<option value='$path'>$path</option>";
    }
    exit();
}
// Handle copy operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_item'])) {
    error_log("Copy item request received.");
    $type = $_POST['type']; // 'folder' or 'file'
    $name = $_POST['name']; // Full path of the folder or file
    $destination = $_POST['destination']; // Destination path

    // Construct source and destination paths
    $source_path = normalizePath($name);
    $destination_path = normalizePath($faculty_folder . '/' . $destination . '/' . basename($name));

    // Ensure the destination is within the faculty's directory
    if (strpos($destination_path, $faculty_folder) !== 0) {
        error_log("Invalid destination path: $destination_path");
        echo json_encode(['success' => false, 'message' => 'Error: Invalid destination path.']);
        exit();
    }

    // Ensure the source exists
    if (!file_exists($source_path)) {
        error_log("Source item not found: $source_path");
        echo json_encode(['success' => false, 'message' => 'Error: Source item not found.']);
        exit();
    }

    // Ensure the destination directory exists
    if (!is_dir(dirname($destination_path))) {
        mkdir(dirname($destination_path), 0777, true);
    }

    // Function to recursively copy a folder
    function copyFolder($source, $destination) {
        if (!is_dir($source)) {
            return copy($source, $destination); // Copy single file
        }
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        $dir = opendir($source);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $source . '/' . $file;
                $dstPath = $destination . '/' . $file;
                if (is_dir($srcPath)) {
                    copyFolder($srcPath, $dstPath);
                } else {
                    copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
        return true;
    }

    // Perform the copy operation
    if ($type === 'folder') {
        if (copyFolder($source_path, $destination_path)) {
            $current_time = date("Y-m-d H:i:s");
            $notification = "• [$current_time] Folder '" . basename($source_path) . "' copied to '$destination'.";
            $_SESSION['notifications'][] = $notification;
            echo json_encode(['success' => true, 'message' => 'Copied successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to copy folder.']);
        }
    } else {
        if (copy($source_path, $destination_path)) {
            $current_time = date("Y-m-d H:i:s");
            $notification = "• [$current_time] File '" . basename($source_path) . "' copied to '$destination'.";
            $_SESSION['notifications'][] = $notification;
            echo json_encode(['success' => true, 'message' => 'Copied successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to copy file.']);
        }
    }

    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_item'])) {
    error_log("Move item request received.");
    $type = $_POST['type']; // 'folder' or 'file'
    $name = $_POST['name']; // Full path of the folder or file
    $destination = $_POST['destination']; // Destination path

    // Construct source and destination paths
    $source_path = normalizePath($name);
    $destination_path = normalizePath($faculty_folder . '/' . $destination . '/' . basename($name));

    // Ensure the destination is within the faculty's directory
    if (strpos($destination_path, $faculty_folder) !== 0) {
        error_log("Invalid destination path: $destination_path");
        echo json_encode(['success' => false, 'message' => 'Error: Invalid destination path.']);
        exit();
    }

    // Ensure the source exists
    if (!file_exists($source_path)) {
        error_log("Source item not found: $source_path");
        echo json_encode(['success' => false, 'message' => 'Error: Source item not found.']);
        exit();
    }

    // Ensure the destination exists
    if (!is_dir(dirname($destination_path))) {
        error_log("Destination folder does not exist: " . dirname($destination_path));
        echo json_encode(['success' => false, 'message' => 'Error: Destination folder does not exist.']);
        exit();
    }

    // Move the item
    if ($type === 'folder') {
        if (moveFolder($source_path, $destination_path)) {
             // Add notification for folder move
             $current_time = date("Y-m-d H:i:s");
             $notification = "• [$current_time] Folder '" . basename($source_path) . "' moved to '$destination'.";
             $_SESSION['notifications'][] = $notification;
            echo json_encode(['success' => true, 'message' => 'Moved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move folder.']);
        }
    } else {
        if (rename($source_path, $destination_path)) {
             // Add notification for file move
             $current_time = date("Y-m-d H:i:s");
             $notification = "• [$current_time] File '" . basename($source_path) . "' moved to '$destination'.";
             $_SESSION['notifications'][] = $notification;
            echo json_encode(['success' => true, 'message' => 'Moved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move file.']);
        }
    }

    // Exit to prevent further execution
    exit();
}
// Function to generate breadcrumb links
function generateBreadcrumb($current_folder, $faculty_folder) {
    $breadcrumb = '<a href="faculty_dashboard.php">Home</a>'; // Start with "Home"
    $relative_path = str_replace($faculty_folder, '', $current_folder); // Remove base folder path
    $path_segments = explode('/', trim($relative_path, '/')); // Split into segments

    $accumulated_path = '';
    foreach ($path_segments as $segment) {
        if (!empty($segment)) {
            $accumulated_path .= '/' . $segment;
            $breadcrumb .= ' &gt; <a href="faculty_dashboard.php?folder=' . urlencode($accumulated_path) . '">' . htmlspecialchars($segment) . '</a>';
        }
    }

    return $breadcrumb;
}

// Generate the breadcrumb for the current folder
$breadcrumb = generateBreadcrumb($current_folder, $faculty_folder);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7f6;
        margin: 0;
        padding: 0;
        color: #333;
        background-image:url(https://res.cloudinary.com/dlsavclsy/image/upload/v1739964398/hodimg_caevhu.jpg);
        background-size:cover;
        background-repeat:no-repeat;
        background-position:center;
    }

    .home-button {
    position: fixed; /* Use fixed positioning to keep it visible */
    top: 20px;
    left: 20px;
    background-color:yellow;
    color: black;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    transition: background-color 0.3s ease;
    z-index: 1001; /* Ensure it's above the heading */
}

.home-button:hover {
    background-color: #0056b3;
}

    .dashboard-container {
        background-color: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        max-width: 1000px;
        margin: 60px auto;
        
    }

    h1 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 30px;
        font-size: 32px;
        font-weight: 600;
    }

    h2 {
        color: #34495e;
        margin-bottom: 20px;
        font-size: 24px;
        font-weight: 500;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }

    .upload-form, .folder-form {
        margin-bottom: 30px;
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }

    .upload-form input[type="file"], .folder-form input[type="text"] {
        display: block;
        margin-bottom: 15px;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .upload-form input[type="file"]:focus, .folder-form input[type="text"]:focus {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
        outline: none;
    }

    .upload-form button, .folder-form button {
        padding: 12px 24px;
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .upload-form button:hover, .folder-form button:hover {
        background-color: #0056b3;
        transform: translateY(-2px);
    }

    .message {
        margin-top: 15px;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
    }

    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .file-list, .folder-list {
        margin-top: 20px;
    }

    .file-item, .folder-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        margin-bottom: 10px;
        background-color: #f9f9f9;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .file-item:hover, .folder-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }

    .file-item a {
        color: #007bff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .file-item a:hover {
        color: #0056b3;
        text-decoration: underline;
    }

    .file-item button, .folder-item button {
        background-color: #dc3545;
        color: #fff;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .file-item button:hover, .folder-item button:hover {
        background-color: #c82333;
        transform: translateY(-2px);
    }

    .no-files, .no-folders {
        text-align: center;
        color: #777;
        font-size: 14px;
        margin-top: 20px;
    }

    .folder-item {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background-color: #f9f9f9;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .folder-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }
    .folder-item a{
        font-size: 18px;
        font-weight: 500;
    }
    .folder-item.selected {
        background-color: #e0f7fa;
        border-color: #007bff;
    }

    .active-folder {
        background-color: #f0f0f0;
        font-weight: bold;
        color: #007bff;
        border-left: 3px solid #007bff;
    }

    .back-button {
        background-color: #007bff;
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .back-button:hover {
        background-color: #0056b3;
        transform: translateY(-2px);
    }

    /* Additional Styling for Dropdowns */
    select {
        display: block;
        margin-bottom: 15px;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    select:focus {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
        outline: none;
    }
     /* New layout styles */
     .dashboard-container {
        display: flex;
        gap: 30px; /* Space between left and right columns */
        align-items: flex-start; /* Align items to the top */
        max-width: 1200px; /* Increase the container width */
        margin: 60px auto; /* Center the container */
    }

    .left-panel, .right-panel {
        flex: 1; /* Both panels take equal space */
        background-color: #ffffff; /* White background for panels */
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Adjust form styles for the left panel */
    .left-panel .upload-form,
    .left-panel .folder-form {
        margin-bottom: 20px;
    }

    /* Ensure the back button stays at the top */
    .back-button {
        margin-bottom: 20px;
    }

    /* Responsive design for smaller screens */
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column; /* Stack panels vertically on small screens */
        }
        .left-panel, .right-panel {
            width: 100%; /* Full width for stacked panels */
        }
    }
    .heading-container {
        position: relative; /* Ensure this is set */
    background-color: #007bff;
    color: white;
    padding: 20px 20px 20px 40px;
    text-align: center;
    width: 100%;
    z-index: 1000;
    height: 70px;
}


.notification-icon {
    position: absolute;
    top: 40px;
    right: 100px;
    cursor: pointer;
    font-size: 30px;
    color: yellow;
    z-index: 1002; /* Higher than other elements */
   
}

.notification-count {
    position: absolute;
    top: -10px;
    right: -10px;
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
}

.notification-dropdown {
    display: none;
    position: absolute;
    top: 60px;
    right: 20px;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    width: 300px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1001;
}
.file-item .copy-button, .folder-item .copy-button {
    background-color: #28a745; /* Green color */
    margin-left: 10px;
}

.file-item .copy-button:hover, .folder-item .copy-button:hover {
    background-color: #218838; /* Darker green on hover */
}
.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    color: #333;
}
.remove-notification {
    background: none;
    border: none;
    color: dark red;
    font-size: 40px;
    cursor: pointer;
    padding: 0 5px;
}

.remove-notification:hover {
    color: #c82333;
}

.clear-all-button {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 10px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.clear-all-button:hover {
    background-color: #c82333;
}
.notification-item:last-child {
    border-bottom: none;
}
        .heading-container h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600;
            color: yellow;
        }

        .heading-container h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 500;
            color: #f0f0f0; /* Light gray for the branch name */
        }

        /* Adjust the dashboard container to add margin-top */
        .dashboard-container {
            margin-top: 100px; /* Add space below the heading */
        }
        .notifications-panel {
    flex: 1;
    background-color: #ffffff;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.notification-list {
    margin-top: 20px;
}

.notification-item {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    color: #333;
}

.notification-item:last-child {
    border-bottom: none;
}

#moveItemModal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    z-index: 1002;
    width: 500px; /* Increased width */
    max-height: 80vh; /* Increased height */
    overflow-y: auto; /* Scrollable if content exceeds height */
}

#moveItemModal h3 {
    margin-top: 0;
    font-size: 24px;
    margin-bottom: 20px;
}

#moveItemModal select {
    display: block;
    margin-bottom: 15px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    width: 100%;
    font-size: 16px; /* Larger font size */
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

#moveItemModal select:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
    outline: none;
}

#moveItemModal button {
    padding: 12px 24px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px; /* Larger font size */
    font-weight: 500;
    transition: background-color 0.3s ease, transform 0.2s ease;
    margin-right: 10px;
}

#moveItemModal button:hover {
    background-color: #0056b3;
    transform: translateY(-2px);
}

#moveItemModal input[type="text"] {
    display: block;
    margin-bottom: 15px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    width: 100%;
    font-size: 16px; /* Larger font size */
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

#moveItemModal input[type="text"]:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
    outline: none;
}
.breadcrumb {
    margin-bottom: 20px;
    font-size: 20px;
    color: #555;
    padding: 10px;
    background-color: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding-left:50px;
}

.breadcrumb a {
    color: blue;
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.breadcrumb .active {
    color: #333;
    font-weight: bold;
}
/* Breadcrumb Search Results */
#breadcrumbSearchResults {
    max-height: 200px;
    overflow-y: auto;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 5px;
}

#breadcrumbSearchResults a {
    display: block;
    padding: 5px 10px;
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

#breadcrumbSearchResults a:hover {
    background-color: #f1f1f1;
    color: #0056b3;
}

/* Highlight matching terms in search results */
#breadcrumbSearchResults .highlight {
    background-color: #007bff;
    color: white;
    padding: 2px 5px;
    border-radius: 3px;
}
    </style>
</head>
<body>
<a href="logout.php" class="home-button">🚪 Logout</a>
    <div class="heading-container">
        <h1>WELCOME, <?php echo $username; ?></h1>
        <h2><?php echo ucfirst($branch); ?> FACULTY DASHBAORD</h2>
        <div class="notification-icon" onclick="toggleNotifications()">
        🔔
    <span class="notification-count">0</span>
</div>
    </div>
    <div class="notification-dropdown">
        <?php if (!empty($_SESSION['notifications'])): ?>
            <button onclick="clearNotifications()">Clear All</button>
            <?php foreach ($_SESSION['notifications'] as $notification): ?>
                <div class="notification-item"><?php echo htmlspecialchars($notification); ?></div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notification-item">No new notifications</div>
        <?php endif; ?>
    </div>
      <!-- Breadcrumb -->
<div class="breadcrumb" style="background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
    <input type="text" id="breadcrumbSearch" placeholder="Search folder path..." style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; font-size: 14px;">
    <div id="breadcrumbLinks">
        <?php echo $breadcrumb; ?>
    </div>
    <!-- Container for search result paths -->
    <div id="breadcrumbSearchResults" style="margin-top: 10px;"></div>
</div>
    <div class="dashboard-container">
        <!-- Breadcrumb -->
   
        <!-- Left Panel: Forms -->
        <div class="left-panel" id="forms-panel">
             
            <!-- Create Main Folder Form -->
            <h2>Create Main Folder</h2>
            <form class="folder-form" action="" method="POST">
               <!-- <select name="main_folder_name" id="main_folder_dropdown">
                    <option value="">Select Main Folder</option>
                    <?php
                    $initial_folders = array_diff(scandir($faculty_folder), ['.', '..']);
                    foreach ($initial_folders as $folder):
                        if (is_dir($faculty_folder . '/' . $folder)):
                    ?>
                        <option value="<?php echo $folder; ?>"><?php echo $folder; ?></option>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </select>-->
                <input type="text" name="new_folder_name" id="new_folder_input" placeholder="Enter new folder name">
                <button type="submit" name="create_main_folder">Create</button>
            </form>

            <!-- Create Subfolder Form -->
            <h2>Create Subfolder</h2>
            <form class="folder-form" action="" method="POST">
                <select name="parent_folder" required>
                    <option value="">Select Parent Folder</option>
                    <?php
                    $all_folders = listFoldersRecursively($current_folder, $faculty_folder);
                    foreach ($all_folders as $folder):
                        $folder_name = basename($folder);
                    ?>
                        <option value="<?php echo $folder_name; ?>"><?php echo $folder_name; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="sub_folder_name" placeholder="Enter subfolder name" required>
                <button type="submit" name="create_sub_folder">Create Subfolder</button>
            </form>

            <!-- File Upload Form -->
            <h2>Upload Files</h2>
            <form class="upload-form" action="" method="POST" enctype="multipart/form-data">
                <select name="upload_folder" required>
                    <option value="">Select Folder</option>
                    <?php
                    $all_folders = listFoldersRecursively($current_folder, $faculty_folder);
                    foreach ($all_folders as $folder):
                        $folder = trim($folder, '/');
                    ?>
                        <option value="<?php echo $folder; ?>"><?php echo $folder; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="file" required>
                <button type="submit">Upload</button>
            </form>

            <!-- Display Messages -->
            <?php if (isset($message)): ?>
                <p class="message"><?php echo $message; ?></p>
            <?php endif; ?>
        </div>

        <!-- Right Panel: Folders and Files -->
        <div class="right-panel"   id="files-panel">
            <!-- Back Button -->
            <?php if (isset($_GET['folder']) && $_GET['folder'] !== ""): ?>
                <?php
                $parent_folder = dirname($_GET['folder']);
                $parent_folder = $parent_folder === '.' ? '' : $parent_folder;
                ?>
                <a href="faculty_dashboard.php?folder=<?php echo urlencode($parent_folder); ?>" class="back-button">⬅ Back</a>
            <?php endif; ?>

            <!-- List of Folders -->
            <h2>Folders</h2>
            <div class="folder-list">
                <?php if (!empty($folders)): ?>
                    <?php foreach ($folders as $folder): ?>
                        <div class="folder-item">
                            <?php
                            $folder_path = isset($_GET['folder']) ? $_GET['folder'] . '/' . $folder : $folder;
                            ?>
                            <a href="faculty_dashboard.php?folder=<?php echo urlencode($folder_path); ?>">📁 <?php echo htmlspecialchars($folder); ?></a>
                            <button onclick="deleteFolder('<?php echo addslashes($folder); ?>')">Delete Folder</button>
                            
                <button onclick="moveItem('folder', '<?php echo addslashes($folder); ?>')">Move</button>
                <button class="copy-button" onclick="copyItem('folder', '<?php echo addslashes($folder); ?>')">Copy</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-folders">No folders created yet.</p>
                <?php endif; ?>
            </div>

            <!-- List of Files -->
            <h2>Files</h2>
            <div class="file-list">
                <?php if (!empty($files)): ?>
                    <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <a href="<?php echo $current_folder . '/' . $file; ?>" target="_blank">📄 <?php echo $file; ?></a>
                            <button onclick="deleteFile('<?php echo $file; ?>')">Delete</button>
                            <button onclick="moveItem('file', '<?php echo $file; ?>')">Move</button>
                            <button class="copy-button" onclick="copyItem('file', '<?php echo $file; ?>')">Copy</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-files">No files in this folder.</p>
                <?php endif; ?>
            </div>
        </div>
<!-- Move Item Modal -->
<div id="moveItemModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 30px; border-radius: 15px; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); z-index: 1002; width: 500px; max-height: 80vh; overflow-y: auto;">
    <h3>Move Item</h3>
    <!-- Add a search input field -->
    <input type="text" id="searchDestination" placeholder="Search destination..." oninput="filterPaths()" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px;">
    <select id="destinationFolder" size="10" style="width: 100%; margin-bottom: 15px;">
        <option value="">Select Destination Folder</option>
    </select>
    <button onclick="confirmMove()">Move</button>
    <button onclick="closeMoveModal()">Cancel</button>
</div>
        <div class="notifications-panel" id="notifications-panel" style="display: none;">
    <h2>Notifications</h2>
    <button onclick="clearNotifications()" class="clear-all-button">Clear All</button>
    <div class="notification-list">
        <?php if (!empty($_SESSION['notifications'])): ?>
            <?php foreach ($_SESSION['notifications'] as $index => $notification): ?>
                <div class="notification-item">
                    <span><?php echo htmlspecialchars($notification); ?></span>
                    <button class="remove-notification" onclick="removeNotification(<?php echo $index; ?>)">×</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notification-item">No new notifications</div>
        <?php endif; ?>
    </div>
</div>
    </div>

    <script>
        // Function to fetch all paths dynamically via PHP
function getAllPaths() {
    return new Promise((resolve) => {
        fetch('faculty_dashboard.php?action=get_all_paths')
            .then(response => response.text())
            .then(data => {
                // Parse the returned options into an array of paths
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const options = Array.from(doc.querySelectorAll('option')).map(option => option.value);
                resolve(options);
            })
            .catch(error => {
                console.error('Error fetching paths:', error);
                resolve([]);
            });
    });
}

// Function to filter and update breadcrumb based on search input
async function filterBreadcrumb() {
    const searchInput = document.getElementById('breadcrumbSearch').value.toLowerCase();
    const breadcrumbLinks = document.getElementById('breadcrumbLinks');
    const breadcrumbSearchResults = document.getElementById('breadcrumbSearchResults');
    const currentFolder = "<?php echo isset($_GET['folder']) ? $_GET['folder'] : ''; ?>";
    const folderSegments = currentFolder ? currentFolder.split('/').filter(segment => segment) : [];

    // Default breadcrumb (without search)
    let breadcrumbHTML = '<a href="faculty_dashboard.php">Home</a>';
    let breadcrumbPath = '';
    folderSegments.forEach((segment) => {
        breadcrumbPath += segment + '/';
        breadcrumbHTML += ` > <a href="faculty_dashboard.php?folder=${encodeURIComponent(breadcrumbPath)}">${htmlspecialchars(segment)}</a>`;
    });
    breadcrumbLinks.innerHTML = breadcrumbHTML;

    // Handle search functionality
    if (searchInput) {
        // Fetch all paths dynamically
        const allPaths = await getAllPaths();
        
        // Filter paths that contain the search term
        const matchingPaths = allPaths.filter(path => path.toLowerCase().includes(searchInput));

        if (matchingPaths.length > 0) {
            let searchResultsHTML = '';
            matchingPaths.forEach((path) => {
                // Highlight the matching part of the path
                const highlightedPath = path.replace(
                    new RegExp(`(${searchInput})`, 'gi'),
                    '<span class="highlight">$1</span>'
                );
                searchResultsHTML += `<a href="faculty_dashboard.php?folder=${encodeURIComponent(path)}">${highlightedPath}</a>`;
            });
            breadcrumbSearchResults.innerHTML = searchResultsHTML;
            breadcrumbSearchResults.style.display = 'block';
        } else {
            breadcrumbSearchResults.innerHTML = '<p>No matching paths found.</p>';
            breadcrumbSearchResults.style.display = 'block';
        }
    } else {
        breadcrumbSearchResults.innerHTML = '';
        breadcrumbSearchResults.style.display = 'none';
    }
}

// Helper function to escape HTML special characters
function htmlspecialchars(str) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, char => map[char]);
}

// Attach event listener to the breadcrumb search input
document.getElementById('breadcrumbSearch').addEventListener('input', filterBreadcrumb);

// Call filterBreadcrumb on page load to initialize the breadcrumb
document.addEventListener('DOMContentLoaded', filterBreadcrumb);

// Update notification count on page load
document.addEventListener('DOMContentLoaded', updateNotificationCount);
let currentItemType = '';
let currentItemName = '';
let currentAction = ''; // 'move' or 'copy'
function moveItem(type, name) {
    currentItemType = type;
    currentItemName = name;
    currentAction = 'move';
    // Fetch the current folder from PHP
    const currentFolder = "<?php echo $current_folder; ?>";

    // Construct the full source path
    const sourcePath = `${currentFolder}/${name}`;

    fetch('faculty_dashboard.php?get_paths=true')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const dropdown = document.getElementById('destinationFolder');
            dropdown.innerHTML = '<option value="">Select Destination Folder</option>';
            
            data.paths.forEach(path => {
                const option = document.createElement('option');
                option.value = path;
                option.textContent = path;
                dropdown.appendChild(option);
            });

            document.getElementById('moveItemModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching paths:', error);
            alert('Error fetching paths.');
        });
}
function copyItem(type, name) {
    currentItemType = type;
    currentItemName = name;
    currentAction = 'copy';

    const currentFolder = "<?php echo $current_folder; ?>";
    const sourcePath = `${currentFolder}/${name}`;

    fetch('faculty_dashboard.php?get_paths=true')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const dropdown = document.getElementById('destinationFolder');
            dropdown.innerHTML = '<option value="">Select Destination Folder</option>';
            
            data.paths.forEach(path => {
                const option = document.createElement('option');
                option.value = path;
                option.textContent = path;
                dropdown.appendChild(option);
            });

            document.getElementById('moveItemModal').querySelector('h3').textContent = 'Copy Item';
            document.getElementById('moveItemModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching paths:', error);
            alert('Error fetching paths.');
        });
}
function closeMoveModal() {
    document.getElementById('moveItemModal').style.display = 'none';
}

function confirmMove() {
    const destinationPath = document.getElementById('destinationFolder').value;

    if (!destinationPath) {
        alert('Please select a destination path.');
        return;
    }

    const sourcePath = "<?php echo $current_folder; ?>" + "/" + currentItemName;
    const action = currentAction === 'move' ? 'move_item' : 'copy_item';

    fetch('faculty_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `${action}=true&type=${currentItemType}&name=${encodeURIComponent(sourcePath)}&destination=${encodeURIComponent(destinationPath)}`,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(currentAction === 'move' ? "Moved successfully" : "Copied successfully");
            if (currentAction === 'move') {
                window.location.reload(); // Reload only for move
            } else {
                closeMoveModal(); // Close modal for copy, no reload needed
                updateNotificationCount(); // Update notifications if visible
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred.');
    });
}


function toggleNotifications() {
    const formsPanel = document.getElementById('forms-panel');
    const filesPanel = document.getElementById('files-panel');
    const notificationsPanel = document.getElementById('notifications-panel');

    if (notificationsPanel.style.display === 'none') {
        // Show notifications and hide forms/files
        notificationsPanel.style.display = 'block';
        formsPanel.style.display = 'none';
        filesPanel.style.display = 'none';
    } else {
        // Show forms/files and hide notifications
        notificationsPanel.style.display = 'none';
        formsPanel.style.display = 'block';
        filesPanel.style.display = 'block';
    }
}
function removeNotification(index) {
    fetch('faculty_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'remove_notification=true&index=' + index,
    }).then(response => {
        if (response.ok) {
            // Remove the notification item from the DOM
            const notificationItem = document.querySelector(`.notification-item:nth-child(${index + 1})`);
            if (notificationItem) {
                notificationItem.remove();
            }
            updateNotificationCount();
        }
    });
}

function clearNotifications() {
    fetch('faculty_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'clear_all_notifications=true',
    }).then(response => {
        if (response.ok) {
            // Clear all notification items from the DOM
            const notificationList = document.querySelector('.notification-list');
            notificationList.innerHTML = '<div class="notification-item">No new notifications</div>';
            updateNotificationCount();
        }
    });
}

function updateNotificationCount() {
    const notificationCount = document.querySelector('.notification-count');
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationCount.textContent = notificationItems.length > 1 ? notificationItems.length - 1 : 0;
}

    // Close the dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.notification-icon')) {
            const dropdowns = document.querySelectorAll('.notification-dropdown');
            dropdowns.forEach(dropdown => {
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            });
        }
    }
        function deleteFile(fileName) {
            if (confirm("Are you sure you want to delete this file?")) {
                window.location.href = "faculty_dashboard.php?delete=" + encodeURIComponent(fileName) + "&folder=<?php echo urlencode($_GET['folder'] ?? ''); ?>";
            }
        }

        function deleteFolder(folderName) {
            if (confirm("Are you sure you want to delete this folder and all its contents?")) {
                window.location.href = "faculty_dashboard.php?delete_folder=" + encodeURIComponent(folderName) + "&folder=<?php echo urlencode($_GET['folder'] ?? ''); ?>";
            }
        }
        function filterPaths() {
            const searchQuery = document.getElementById('searchDestination').value.toLowerCase();
    const options = document.querySelectorAll('#destinationFolder option');

    options.forEach(option => {
        const path = option.textContent.toLowerCase();
        if (path.includes(searchQuery)) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}
    </script>
</body>