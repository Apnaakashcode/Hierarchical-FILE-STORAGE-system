<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
// Check if the user is logged in and is the principal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header("Location: login.php");
    exit();
}

// Initialize the $message variable to avoid undefined variable warnings
$message = "";
// Function to add a notification
function addNotification($message) {
    $notificationFile = 'notifications.txt';
    $timestamp = date('Y-m-d H:i:s'); // This will now use Indian time
    // Remove the 'uploads/principal/' prefix from the message
    $message = str_replace('uploads/principal/', '', $message);
    $notificationMessage = "• [$timestamp] $message\n";
    file_put_contents($notificationFile, $notificationMessage, FILE_APPEND);
}

// Function to read notifications
function readNotifications() {
    $notificationFile = 'notifications.txt';
    if (file_exists($notificationFile)) {
        $notifications = file($notificationFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $notifications;
    }
    return ["No notifications found."];
}
if (isset($_GET['delete_notification'])) {
    $notificationContent = urldecode($_GET['delete_notification']);
    error_log("Deleting notification: " . $notificationContent); // Debugging
    deleteNotification($notificationContent);
    header("Location: principal_dashboard.php"); // Refresh the page
    exit();
}

function deleteNotification($notificationContent) {
    $notificationFile = 'notifications.txt';
    if (file_exists($notificationFile)) {
        $notifications = file($notificationFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updatedNotifications = array_filter($notifications, function($notification) use ($notificationContent) {
            return trim($notification) !== trim($notificationContent);
        });
        file_put_contents($notificationFile, implode("\n", $updatedNotifications) . "\n");
    }
}
// Handle "Clear All Notifications" action
if (isset($_POST['clear_all_notifications'])) {
    $notificationFile = 'notifications.txt';
    if (file_exists($notificationFile)) {
        if (file_put_contents($notificationFile, '') !== false) {
            $message = "All notifications cleared successfully.";
        } else {
            $message = "Failed to clear notifications.";
        }
    } else {
        $message = "No notifications found.";
    }
    // Redirect back to the dashboard
    header("Location: principal_dashboard.php");
    exit();
}
$principal_username = $_SESSION['username'];
$uploads_folder = "uploads/$principal_username/";

// Get the current folder from the query parameter
$current_folder = isset($_GET['folder']) ? $_GET['folder'] : '';

// Determine the full path of the current folder
if ($current_folder) {
    $current_folder_path = $uploads_folder . $current_folder . '/';
} else {
    $current_folder_path = $uploads_folder;
}

// Create the uploads folder if it doesn't exist
if (!is_dir($uploads_folder)) {
    mkdir($uploads_folder, 0777, true);
}

// Principal's personal folder path
$personal_folder = $uploads_folder . "MY_UPLOADS/";

// Create the personal folder if it doesn't exist
if (!is_dir($personal_folder)) {
    mkdir($personal_folder, 0777, true);
}

// Create the HOD folder if it doesn't exist
$hod_folder = $uploads_folder . "HOD/";
if (!is_dir($hod_folder)) {
    mkdir($hod_folder, 0777, true);
}
// Dynamically fetch HOD usernames from the HOD directory
$hod_list = [];
if (is_dir($hod_folder)) {
    $items = array_diff(scandir($hod_folder), ['.', '..']);
    foreach ($items as $item) {
        $item_path = $hod_folder . $item;
        if (is_dir($item_path)) {
            $hod_list[] = $item; // Add HOD username to the list
        }
    }
}
// Ensure the PRINCIPAL_UPLOADS directory exists for each HOD
foreach ($hod_list as $hod_username) {
    $hod_principal_uploads_path = $hod_folder . "$hod_username/PRINCIPAL_UPLOADS/";
    if (!is_dir($hod_principal_uploads_path)) {
        mkdir($hod_principal_uploads_path, 0777, true);
    }
}

// Handle file deletion
if (isset($_GET['delete'])) {
    $file_to_delete = $_GET['delete'];
    $file_path = $current_folder_path . $file_to_delete;

    if (file_exists($file_path) && is_file($file_path)) {
        if (unlink($file_path)) {
            $message = "File deleted successfully.";
            // Add notification for file deletion
            addNotification("File '$file_to_delete' deleted from Principal's personal folder.");
        } else {
            $message = "Failed to delete file.";
        }
    } else {
        $message = "File not found.";
    }
    header("Location: principal_dashboard.php?folder=" . urlencode($current_folder));
    exit();
}

// Handle folder deletion
if (isset($_GET['delete_folder'])) {
    $folder_to_delete = $_GET['delete_folder'];
    $folder_path = $current_folder_path . $folder_to_delete;

    if (is_dir($folder_path)) {
        // Recursively delete the folder and its contents
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        if (rmdir($folder_path)) {
            $message = "Folder deleted successfully.";
             // Add notification for folder deletion
             addNotification("Folder '$folder_to_delete' deleted from Principal's personal folder.");
        } else {
            $message = "Failed to delete folder.";
        }
    } else {
        $message = "Folder not found.";
    }
    header("Location: principal_dashboard.php?folder=" . urlencode($current_folder));
    exit();
}

// Handle folder creation for Principal's personal folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_main_folder'])) {
    $folder_name = trim($_POST['main_folder_name']);
    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $new_folder_path = $personal_folder . $folder_name . '/';
            if (!is_dir($new_folder_path)) {
                if (!mkdir($new_folder_path, 0777, true)) {
                    $message = "Failed to create folder.";
                } else {
                    $message = "Main folder created successfully.";
                    // Add notification for folder creation
                    addNotification("Main folder '$folder_name' created in Principal's MY_UPLOADS.");
                }
            } else {
                $message = "Folder already exists.";
            }
        } else {
            $message = "Invalid folder name.";
        }
    } else {
        $message = "Folder name cannot be empty.";
    }
}

// Handle subfolder creation for Principal's personal folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sub_folder'])) {
    $parent_folders = $_POST['parent_folder']; // Array of selected parent folders
    $folder_name = trim($_POST['sub_folder_name']);

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($parent_folders as $parent_folder) {
                $new_folder_path = $personal_folder . $parent_folder . '/' . $folder_name . '/';
                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                         // Add notification for subfolder creation
                         addNotification("Subfolder '$folder_name' created in '$parent_folder' in Principal's MY_UPLOADS.");
                    }
                } else {
                    $error_count++;
                }
            }

            if ($success_count > 0) {
                $message = "Subfolder created successfully in $success_count folder(s).";
            }
            if ($error_count > 0) {
                $message .= " Failed to create subfolder in $error_count folder(s).";
            }
        } else {
            $message = "Invalid folder name.";
        }
    } else {
        $message = "Subfolder name cannot be empty.";
    }
}

// Handle file upload for Principal's personal folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['principal_upload'])) {
    $selected_folders = $_POST['principal_folder']; // Array of selected folders
    $upload_files = $_FILES['principal_file'];

    if (!empty($selected_folders) && !empty($upload_files['name'][0])) {
        $success_count = 0;
        $error_count = 0;

        // Loop through each file
        foreach ($upload_files['tmp_name'] as $index => $tmp_name) {
            $file_name = basename($upload_files['name'][$index]);

            // Loop through each selected folder
            foreach ($selected_folders as $selected_folder) {
                $upload_path = $personal_folder;

                // Append selected folder to the upload path if specified
                if (!empty($selected_folder)) {
                    $upload_path .= $selected_folder . '/';
                }

                // Ensure the folder exists
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $file_path = $upload_path . $file_name;

                // Copy the file instead of moving it
                if (copy($tmp_name, $file_path)) {
                    $success_count++;
                     // Add notification for file upload
                     addNotification("File '$file_name' uploaded to '$selected_folder' in Principal's MY_UPLOADS.");
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $message = "File(s) uploaded successfully to $success_count folder(s).";
        }
        if ($error_count > 0) {
            $message .= " Failed to upload file(s) to $error_count folder(s).";
        }
    } else {
        $message = "No file selected or upload error.";
    }
}

// Handle folder creation for HOD

// Handle folder creation for HOD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_hod_main_folder'])) {
    $selected_hods = $_POST['hod_main_folder_name']; // Array of selected HOD usernames
    $folder_name = trim($_POST['hod_main_folder_name_input']); // New folder name

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($selected_hods as $hod_username) {
                // Construct the path for the selected HOD's PRINCIPAL_UPLOADS folder
                $new_folder_path = $hod_folder . "$hod_username/PRINCIPAL_UPLOADS/$folder_name/";

                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                        addNotification("New folder '$folder_name' created for $hod_username in HOD.");
                    }
                } else {
                    $error_count++;
                }
            }

            if ($success_count > 0) {
                $message = "Main folder created successfully for $success_count HOD(s).";
            }
            if ($error_count > 0) {
                $message .= " Failed to create main folder for $error_count HOD(s).";
            }
        } else {
            $message = "Invalid folder name.";
        }
    } else {
        $message = "Folder name cannot be empty.";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_hod_sub_folder'])) {
    $selected_hods = $_POST['hod_parent_folder']; // Array of selected parent folders (full paths)
    $folder_name = trim($_POST['hod_sub_folder_name']); // New subfolder name

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($selected_hods as $parent_folder) {
                // Construct the full path for the new subfolder
                $new_folder_path = $hod_folder . $parent_folder . '/' . $folder_name . '/';

                // Ensure the parent folder exists
                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                         // Add notification for subfolder creation
                         addNotification("New subfolder '$folder_name' created in $parent_folder for HOD.");
                    }
                } else {
                    $error_count++;
                }
            }

            if ($success_count > 0) {
                $message = "Subfolder created successfully for $success_count HOD(s).";
            }
            if ($error_count > 0) {
                $message .= " Failed to create subfolder for $error_count HOD(s).";
            }
        } else {
            $message = "Invalid folder name.";
        }
    } else {
        $message = "Subfolder name cannot be empty.";
    }
}
// Handle file upload for HOD folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_to_hod'])) {
    $selected_hods = $_POST['hod_folder']; // Array of selected HOD folders (relative paths)
    $upload_files = $_FILES['hod_file'];

    if (!empty($selected_hods) && !empty($upload_files['name'][0])) {
        $success_count = 0;
        $error_count = 0;

        // Loop through each file
        foreach ($upload_files['tmp_name'] as $index => $tmp_name) {
            $file_name = basename($upload_files['name'][$index]);

            // Loop through each selected HOD folder
            foreach ($selected_hods as $selected_folder) {
                // Construct the full upload path
                $upload_path = $hod_folder . $selected_folder;

                // Ensure the folder exists
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $file_path = $upload_path . $file_name;

                // Copy the file instead of moving it
                if (copy($tmp_name, $file_path)) {
                    $success_count++;
                    addNotification("New file '$file_name' uploaded to $selected_folder in HOD.");
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $message = "File(s) uploaded successfully for $success_count HOD(s).";
        }
        if ($error_count > 0) {
            $message .= " Failed to upload file(s) for $error_count HOD(s).";
        }
    } else {
        $message = "No file selected or upload error.";
    }
}

// Function to generate top-level folder options
function generateTopLevelFolderOptions($base_path) {
    $options = '';
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                // Add only top-level folders as options
                $options .= "<option value='$item'>$item</option>";
            }
        }
    }
    return $options;
}

// Function to generate folder options recursively
function generateFolderOptions($base_path, $prefix = '') {
    $options = '';
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                // Add the current folder as an option with the relative path
                $relative_path = $prefix . $item . '/';
                $options .= "<option value='$relative_path'>$relative_path</option>";
                // Recursively add subfolders
                $options .= generateFolderOptions($item_path . '/', $relative_path);
            }
        }
    }
    return $options;
}
// Function to recursively get all folder paths starting from a base path
// Function to recursively get all folder paths starting from a base path
function getAllFolderPaths($base_path, $prefix = '') {
    $folderPaths = [];
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                // Add the current folder path
                $relative_path = $prefix . $item . '/';
                $folderPaths[] = $relative_path;

                // Recursively add subfolders
                $folderPaths = array_merge($folderPaths, getAllFolderPaths($item_path . '/', $relative_path));
            }
        }
    }
    return $folderPaths;
}

// Get all folder paths for the move destination dropdown
// Get all folder paths for the move destination dropdown
$allFolderPaths = [];
// Add paths from Principal's personal folder (MY_UPLOADS)
$allFolderPaths = array_merge($allFolderPaths, getAllFolderPaths($personal_folder, 'MY_UPLOADS/'));
// Add paths from HOD folders
$allFolderPaths = array_merge($allFolderPaths, getAllFolderPaths($hod_folder, 'HOD/'));

// Handle the get_all_paths action for AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_all_paths') {
    $all_paths = [];
    // Add paths from Principal's personal folder (MY_UPLOADS)
    $all_paths = array_merge($all_paths, getAllFolderPaths($personal_folder, 'MY_UPLOADS/'));
    // Add paths from HOD folders
    foreach ($hod_list as $hod_username) {
        $hod_principal_uploads_path = $hod_folder . "$hod_username/PRINCIPAL_UPLOADS/";
        $all_paths = array_merge($all_paths, getAllFolderPaths($hod_principal_uploads_path, "HOD/$hod_username/PRINCIPAL_UPLOADS/"));
    }

    // Output the options
    foreach ($all_paths as $path) {
        echo "<option value='$path'>$path</option>";
    }
    exit();
}
// Handle copy operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_item'])) {
    $item = $_POST['item'];
    $sourcePath = $_POST['sourcePath'];
    $destination = $_POST['destination'];
    $isFolder = $_POST['isFolder'] === 'true';

    error_log("Source Path: $sourcePath"); // Debugging
    error_log("Destination Path: $destination"); // Debugging

    // Check if the source path exists
    if (!file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'message' => 'Source file or folder does not exist.']);
        exit();
    }

    // Check if the destination path exists, create it if it doesn’t
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    // Destination path for the item
    $destinationPath = $destination . '/' . basename($item);

    // Function to recursively copy directories
    function copyFolder($src, $dst) {
        if (!is_dir($src)) {
            return copy($src, $dst); // Copy single file
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
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
    if ($isFolder) {
        $success = copyFolder($sourcePath, $destinationPath);
    } else {
        $success = copy($sourcePath, $destinationPath);
    }

    if ($success) {
        if ($isFolder) {
            addNotification("Folder '$item' copied from '$sourcePath' to '$destination'.");
        } else {
            addNotification("File '$item' copied from '$sourcePath' to '$destination'.");
        }
        echo json_encode(['success' => true, 'message' => $isFolder ? 'Folder copied successfully.' : 'File copied successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to copy ' . ($isFolder ? 'folder.' : 'file.')]);
    }
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_item'])) {
    $item = $_POST['item'];
    $sourcePath = $_POST['sourcePath'];
    $destination = $_POST['destination'];
    $isFolder = $_POST['isFolder'] === 'true';

    error_log("Source Path: $sourcePath"); // Debugging
    error_log("Destination Path: $destination"); // Debugging

    // Check if the source path exists
    if (!file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'message' => 'Source file or folder does not exist.']);
        exit();
    }

    // Check if the destination path exists
    if (!is_dir($destination)) {
        echo json_encode(['success' => false, 'message' => 'Destination path does not exist.']);
        exit();
    }

     // Move the item
     if (rename($sourcePath, $destination . '/' . basename($item))) {
        // Add notification for successful move
        if ($isFolder) {
            addNotification("Folder '$item' moved from '$sourcePath' to '$destination'.");
        } else {
            addNotification("File '$item' moved from '$sourcePath' to '$destination'.");
        }

        echo json_encode(['success' => true, 'message' => $isFolder ? 'Folder moved successfully.' : 'File moved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move ' . ($isFolder ? 'folder.' : 'file.')]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-image: url(https://res.cloudinary.com/dlsavclsy/image/upload/v1739964398/hodimg_caevhu.jpg);
    background-size: cover; /* Cover the entire screen */
    background-attachment: fixed; /* Fix the background */
    background-repeat: no-repeat;
    background-position: center;
    margin: 0;
    padding: 0;
    color: #333;
        }

        .dashboard-container {
            background-color: transparent /* Slightly more opaque */
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    width: calc(100% - 240px); /* Adjust width to account for sidebar */
    margin-left: 220px; /* Add margin to avoid overlap with sidebar */
    margin-top: 20px; /* Add margin at the top */
        }
          /* Sidebar Styling */
.sidebar {
    width: 200px;
    background: linear-gradient(135deg, #2c3e50, #34495e); /* Gradient background */
    color: white;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 20px;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    z-index: 999; /* Ensure it's above other elements */
}

.sidebar ul {
    list-style: none;
    padding: 0;
    margin-top: 60px; /* Add space below the heading */
}

.sidebar ul li {
    margin-bottom: 15px;
}

.sidebar ul li a {
    color: white;
    text-decoration: none;
    font-size: 16px;
    transition: color 0.3s ease;
}

.sidebar ul li a:hover {
    color: #007bff;
}
/* Updated CSS for Heading */
.dashboard-heading {
    text-align: center;
    font-size: 28px;
    margin-top: 20px;
    margin-bottom: 30px;
    color: #2c3e50;
}
 /* Hide sections by default */
 #principal-section{
    background-color: rgba(173, 216, 230, 0.9); /* Light blue background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
    margin-left: 100px; /* Adjust for sidebar */
    width: calc(100% - 240px); /* Adjust width */
        }
        #hod-section {
    background-color: rgba(255, 182, 193, 0.9); /* Light pink background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
    margin-left: 100px; /* Adjust for sidebar */
    width: calc(100% - 240px); /* Adjust width */
}

#principal-section,
#hod-section {
    display: none;
}
        /* Notifications Container */
.notifications-container {
    background-color: white; /* White background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    width: 80%; /* Adjust width */
    max-height: 500px; /* Limit height */
    overflow-y: auto; /* Add scrollbar if content overflows */
    position: fixed; /* Center the container */
    top: 15%; /* Fix the upper side */
    left: 51%; /* Center horizontally */
    transform: translateX(-50%); /* Adjust for exact center */
    z-index: 1000; /* Ensure it's above other elements */
    display: none; /* Hidden by default */
    margin-left: 100px; /* Add margin to avoid overlap with sidebar */
}

.notifications-list {
    list-style-type: disc; /* Add bullet points */
    padding-left: 20px; /* Add padding for bullet points */
}

.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

.notification-item:last-child {
    border-bottom: none;
}

.delete-notification {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 16px;
}

.delete-notification:hover {
    color: #c82333;
}

#notificationSearch {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

        h1, h2, h3, h4 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            font-size: 28px;
           
        }

        h2 {
            font-size: 24px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        h3 {
            font-size: 20px;
            margin-top: 20px;
        }

        h4 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .home-button {
            position: absolute; /* Fixed position */
    top: 20px; /* Adjust top position */
    right: 30px; /* Move to the right */
    background-color: pink; /* Blue background */
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    transition: background-color 0.3s ease;
    z-index: 1000; /* Ensure it's above other elements */
        }

        .home-button:hover {
            background-color: #0056b3;
        }

        .back-button {
            background-color:yellow;
            margin-left:40px;
            color: black;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #0056b3;
        }

        /* Panels */
        .panel-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .left-panel, .right-panel {
            background-color:light yellow; /* Slightly more opaque */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px; /* Add space below panels */
        }

        .left-panel {
            margin-right: 10px; /* Add space between left and right panels */
        }

        .right-panel {
            margin-left: 10px; /* Add space between left and right panels */
        }

        /* Forms */
        .upload-form, .folder-form {
            margin-bottom: 30px;
    background-color: rgba(255, 255, 255, 0.9); /* Semi-transparent white */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .upload-form input[type="file"], .folder-form input[type="text"], select {
            display: block;
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    width: 100%;
    max-width: 400px;
    font-size: 14px;
    transition: border-color 0.3s ease;
        }

        .upload-form input[type="file"]:focus, .folder-form input[type="text"]:focus, select:focus {
            border-color: #007bff;
            outline: none;
        }

        .upload-form button, .folder-form button {
            padding: 10px 20px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s ease;
        }

        .upload-form button:hover, .folder-form button:hover {
            background-color: #0056b3;
        }
        .dropdown-search {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .highlight {
            background-color: #007bff;
            color: white;
            padding: 2px;
            border-radius: 3px;
        }
        .dropdown-wrapper {
            display: flex;
    align-items: center;
    gap: 10px; /* Space between dropdown and button */
    margin-bottom: 20px; /* Match other form elements */
    width: 100%; /* Ensure the wrapper takes full width */
}

        .dropdown-wrapper select {
            flex: 1; /* Allow dropdown to take remaining space */
    width: 100%; /* Full width */
    min-width: 300px; /* Set a minimum width */
    padding: 12px; /* Increased padding */
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #fff;
    font-size: 16px; /* Increased font size */
    transition: border-color 0.3s ease;
    margin-bottom: 15px;
}
#hod-section .dropdown-wrapper select,
#principal-section .dropdown-wrapper select {
    min-width: 400px; /* Increase minimum width for larger dropdowns */
}

/* Ensure the dropdowns in the HOD and Faculty sections expand */
#hod-section .dropdown-wrapper,
#principal-section .dropdown-wrapper {
    width: 100%; /* Ensure the wrapper takes full width */
}
        .dropdown-wrapper button {
            padding: 10px 15px;
    background-color: #007bff;
    color: white;
    border: none;
    margin-left:30px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s ease;
        }

        .dropdown-wrapper button:hover {
            background-color: #0056b3;
        }

        /* Files and Folders Section */
        .files-folders-section {
            margin-top: 20px;
            width:40vw;
            margin-left:50px;
    background-color: lightyellow; /* Slightly more opaque */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(55, 174, 47, 0.05);
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
    border-radius: 5px;
    margin-bottom: 10px;
    background-color: rgba(255, 255, 255, 0.9); /* Semi-transparent white */
    transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .file-item:hover, .folder-item:hover {
            transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .file-item a, .folder-item a {
            color: #007bff;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .file-item a:hover, .folder-item a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .file-item button, .folder-item button {
            background-color: red;
    color: #fff;
    border: none;
    padding: 8px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    margin-left: 8px;
    transition: background-color 0.3s ease;
        }

        .file-item button:hover, .folder-item button:hover {
            background-color: #c82333;
        }
        /* Move the Delete button closer to the Move button */
.file-item .delete-button, .folder-item .delete-button {
    margin-left: 180px; /* Adjust this value to control the spacing */
}

/* Move button styling */
.file-item .move-button, .folder-item .move-button {
    margin-left: 10px; /* Adjust this value to control the spacing */
    background-color:green;
}

        .no-files, .no-folders {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-top: 20px;
        }

        select[multiple] {
            width: 100%;
    max-width: 500px; /* Increased width */
    padding: 12px; /* Increased padding */
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #fff;
    font-size: 16px; /* Increased font size */
    transition: border-color 0.3s ease;
    margin-bottom: 15px;
}

select[multiple]:focus {
    border-color: #007bff;
    outline: none;
}
input[type="file"] {
    margin-bottom: 20px; /* Increased margin */
}

/* Form Group Styling */
.form-group {
    margin-bottom: 25px; /* Increased margin */
}
/* Custom styles for Choices.js dropdowns */
.choices {
    width: 100%; /* Ensure the dropdown takes full width */
    margin-bottom: 15px;
}

.choices__inner {
    min-height: 40px; /* Set a minimum height */
    padding: 8px 12px; /* Add padding */
    border: 1px solid #ddd; /* Add border */
    border-radius: 5px; /* Add border radius */
    background-color: #fff; /* Set background color */
}

.choices__list--multiple .choices__item {
    background-color: #007bff; /* Set background color for selected items */
    border: 1px solid #007bff; /* Set border color for selected items */
    color: #fff; /* Set text color for selected items */
    margin: 2px; /* Add margin */
    padding: 6px 12px; /* Add padding */
    font-size: 16px; /* Increase font size */
    border-radius: 3px; /* Add border radius */
}

.choices__list--dropdown {
    border: 1px solid #ddd; /* Add border to dropdown */
    border-radius: 5px; /* Add border radius */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Add shadow */
    z-index: 1000; /* Ensure dropdown is above other elements */
}
/* Breadcrumb Styling */
.breadcrumb {
    font-size: 18px;
    color: #555;
    padding: 10px;
    background-color: #f9f9f9; /* Light gray background for the breadcrumb */
    border-radius: 5px;
    display: inline-block; /* Ensure the breadcrumb doesn't take full width */
    margin-left:100px;
}

.breadcrumb a {
    color:blue;
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.breadcrumb .separator {
    margin: 0 5px;
    color: #777;
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
      <!-- Sidebar -->
<div class="sidebar">
    <ul>
    <li><a href="#" onclick="showHome()">🏠 Home</a></li>
            <li><a href="#" onclick="showPrincipalSection()">👨‍🏫 Principal Section</a></li>
            <li><a href="#" onclick="showHODSection()">👩‍🏫 HOD Section</a></li>
            <li><a href="#" onclick="showNotifications()">📜 History</a></li> <!-- New Notification Option -->
    </ul>
</div>
<div class="dashboard-container">
<a href="logout.php" class="home-button">🚪 Logout</a>
        <h1 class="dashboard-heading"><?php echo $principal_username; ?>'s Dashboard</h1>

        <!-- Back Button -->
        <?php if ($current_folder): ?>
            <div style="margin-bottom: 20px;">
                <?php
                $parent_folder = dirname($current_folder);
                if ($parent_folder === '.') {
                    $parent_folder = '';
                }
                ?>
                <a href="principal_dashboard.php?folder=<?php echo urlencode($parent_folder); ?>" class="back-button">⬅ Back</a>
            </div>
        <?php endif; ?>
<!-- Breadcrumb Navigation -->
<!-- Breadcrumb Navigation in a White Container -->
<!-- Breadcrumb Navigation in a White Container -->
<div id="breadcrumb-container" style="background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px; margin-left:100px;">
    <input type="text" id="breadcrumbSearch" placeholder="Search folder path..." class="dropdown-search">
    <div class="breadcrumb" id="breadcrumbLinks">
        <a href="principal_dashboard.php">Home</a>
        <?php
        if ($current_folder) {
            $folders = explode('/', $current_folder);
            $path = '';
            foreach ($folders as $folder) {
                if (!empty($folder)) {
                    $path .= $folder . '/';
                    echo ' > <a href="principal_dashboard.php?folder=' . urlencode($path) . '">' . htmlspecialchars($folder) . '</a>';
                }
            }
        }
        ?>
    </div>
    <!-- Container for search result paths -->
    <div id="breadcrumbSearchResults" style="margin-top: 10px;"></div>
</div>
         <!-- Notifications Section -->
<!-- Notifications Section -->
<div class="notifications-container" id="notifications-container" style="display: none;">
    <h2>History</h2>
    <!-- Search Bar -->
    <input type="text" id="notificationSearch" placeholder="Search history..." class="dropdown-search">
    <!-- Clear All Button -->
    <button onclick="clearAllNotifications()" style="background-color: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; margin-bottom: 15px;">Clear All</button>
    <!-- Notifications List -->
    <div class="notifications-list">
        <?php
        $notifications = readNotifications();
        foreach ($notifications as $notification) {
            ?>
            <div class="notification-item">
                <span><?php echo htmlspecialchars($notification, ENT_QUOTES); ?></span>
                <button class="delete-notification" onclick="deleteNotification('<?php echo urlencode($notification); ?>')">❌</button>
            </div>
            <?php
        }
        ?>
    </div>
</div>
        <!-- Panels -->
        <div class="panel-container">
            <!-- Left Panel: Principal's Personal Folder -->
            <div class="left-panel" id="principal-section">
                <h3>Principal's Section</h3>
                <div class="form-group">
                <!-- Create Main Folder -->
                <h4>Create Main Folder</h4>
                <form action="" method="POST">
                    <input type="text" name="main_folder_name" placeholder="Enter main folder name" required>
                    <button type="submit" name="create_main_folder">Create Main Folder</button>
                </form>
            </div>
            <div class="form-group">
                <!-- Create Subfolder -->
                <h4>Create Subfolder</h4>
                <form action="" method="POST">
                    <div class="dropdown-wrapper">
                        <select name="parent_folder[]" multiple required>
                            <option value="" disabled>Select parent folders</option>
                            <?php echo generateFolderOptions($personal_folder); ?>
                        </select>
                        <button type="button" onclick="selectAllOptions(this)">Select All</button>
                    </div>
                    <input type="text" name="sub_folder_name" placeholder="Enter subfolder name" required>
                    <button type="submit" name="create_sub_folder">Create Subfolder</button>
                </form>
            </div>
            <div class="form-group">
                <!-- Upload File -->
                <h4>Upload File</h4>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="dropdown-wrapper">
                        <select name="principal_folder[]" multiple required>
                            <option value="" disabled>Select folders (optional)</option>
                            <option value="">Upload directly to Principal folder</option>
                            <?php echo generateFolderOptions($personal_folder); ?>
                        </select>
                        <button type="button" onclick="selectAllOptions(this)">Select All</button>
                    </div>
                    <input type="file" name="principal_file[]" multiple required>
                    <button type="submit" name="principal_upload">Upload</button>
                </form>
            </div>
            </div>
            <!-- Right Panel: HOD Operations -->
            <div class="right-panel"  id="hod-section">
                <h3>HOD Section</h3>
                <div class="form-group">
                <!-- Create Main Folder for HOD -->
                <h4>Create Main Folder for HOD</h4>
                <form action="" method="POST">
                    <div class="dropdown-wrapper">
                        <select name="hod_main_folder_name[]" multiple required>
                            <option value="" disabled>Select parent folders</option>
                            <?php foreach ($hod_list as $hod_username): ?>
                    <option value="<?php echo $hod_username; ?>"><?php echo $hod_username; ?></option>
                <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="selectAllOptions(this)">Select All</button>
                    </div>
                    <input type="text" name="hod_main_folder_name_input" placeholder="Enter main folder name" required>
                    <button type="submit" name="create_hod_main_folder">Create Main Folder</button>
                </form>
                            </div>
                            <div class="form-group">
                <!-- Create Subfolder for HOD -->
                <h4>Create Subfolder for HOD</h4>
                <form action="" method="POST">
                    <div class="dropdown-wrapper">
                        <select name="hod_parent_folder[]" multiple required>
                            <option value="" disabled>Select parent folders</option>
                            <?php foreach ($hod_list as $hod_username): ?>
        <?php echo generateFolderOptions($hod_folder . "$hod_username/PRINCIPAL_UPLOADS/", "$hod_username/PRINCIPAL_UPLOADS/"); ?>
    <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="selectAllOptions(this)">Select All</button>
                    </div>
                    <input type="text" name="hod_sub_folder_name" placeholder="Enter subfolder name" required>
                    <button type="submit" name="create_hod_sub_folder">Create Subfolder</button>
                </form>
                            </div>
                <!-- Upload File to HOD Folder -->
                <div class="form-group">
                <h4>Upload File to HOD</h4>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="dropdown-wrapper">
                        <select name="hod_folder[]" multiple required>
                            <option value="" disabled>Select HOD folders</option>
                            <?php foreach ($hod_list as $hod_username): ?>
                <?php echo generateFolderOptions($hod_folder . "$hod_username/PRINCIPAL_UPLOADS/", "$hod_username/PRINCIPAL_UPLOADS/"); ?>
            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="selectAllOptions(this)">Select All</button>
                    </div>
                    <input type="file" name="hod_file[]" multiple required>
                    <button type="submit" name="upload_to_hod">Upload</button>
                </form>
            </div>
        </div>

        <!-- Files and Folders Section -->
<div class="files-folders-section" id="files-folders-section">
    <h3>Files and Folders</h3>

   <!-- Destination Path Dropdown -->
   <div class="form-group">
    <label for="destinationPath">Select Destination Path:</label>
    <select id="destinationPath">
        <option value="" disabled selected>Select destination</option>
        <?php
        foreach ($allFolderPaths as $path) {
            echo "<option value='$path'>$path</option>";
        }
        ?>
    </select>
</div>

    <!-- List of Folders -->
    <h4>Folders</h4>
    <div class="folder-list">
        <?php
        $folders = array_diff(scandir($current_folder_path), ['.', '..']);
        $has_folders = false;

        foreach ($folders as $item) {
            $item_path = $current_folder_path . $item;
            if (is_dir($item_path)) {
                $has_folders = true;
                ?>
                <div class="folder-item">
                    <a href="principal_dashboard.php?folder=<?php echo urlencode($current_folder ? $current_folder . '/' . $item : $item); ?>">
                        📁 <?php echo htmlspecialchars($item); ?>
                    </a>
                    <button class="delete-button" onclick="deleteFolder('<?php echo $current_folder ? $current_folder . '/' . $item : $item; ?>')">Delete Folder</button>
                    <button class="move-button" onclick="moveItem('<?php echo $item; ?>', true)">Move</button>
                    <button class="copy-button" onclick="copyItem('<?php echo $item; ?>', true)" style="background-color: #28a745;">Copy</button>
                </div>
                <?php
            }
        }

        if (!$has_folders) {
            echo '<p class="no-folders">No folders found.</p>';
        }
        ?>
    </div>

    <!-- List of Files -->
    <h4>Files</h4>
    <div class="file-list">
        <?php
        $files = array_diff(scandir($current_folder_path), ['.', '..']);
        $has_files = false;

        foreach ($files as $item) {
            $item_path = $current_folder_path . $item;
            if (is_file($item_path)) {
                $has_files = true;
                ?>
                <div class="file-item">
                    <a href="<?php echo $item_path; ?>" target="_blank">📄 <?php echo $item; ?></a>
                    <button class="delete-button" onclick="deleteFile('<?php echo $current_folder ? $current_folder . '/' . $item : $item; ?>')">Delete</button>
                    <button class="move-button" onclick="moveItem('<?php echo $item; ?>', false)">Move</button>
                    <button class="copy-button" onclick="copyItem('<?php echo $item; ?>', false)" style="background-color: #28a745;">Copy</button>
                </div>
                <?php
            }
        }

        if (!$has_files) {
            echo '<p class="no-files">No files found.</p>';
        }
        ?>
    </div>
</div>
        </div>
    </div>

    <script>
        // Function to fetch all paths dynamically via PHP
function getAllPaths() {
    return new Promise((resolve) => {
        fetch('principal_dashboard.php?action=get_all_paths')
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
    let breadcrumbHTML = '<a href="principal_dashboard.php">Home</a>';
    let breadcrumbPath = '';
    folderSegments.forEach((segment) => {
        breadcrumbPath += segment + '/';
        breadcrumbHTML += ` > <a href="principal_dashboard.php?folder=${encodeURIComponent(breadcrumbPath)}">${htmlspecialchars(segment)}</a>`;
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
                searchResultsHTML += `<a href="principal_dashboard.php?folder=${encodeURIComponent(path)}">${highlightedPath}</a>`;
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
        // Function to copy an item (file or folder)
let isCopying = false; // Flag to prevent multiple calls

function copyItem(itemName, isFolder) {
    if (isCopying) return; // Prevent multiple calls
    isCopying = true;

    const destinationPathDropdown = document.getElementById('destinationPath');
    const selectedOption = destinationPathDropdown.options[destinationPathDropdown.selectedIndex];

    if (!destinationPathDropdown || !selectedOption || selectedOption.value === "") {
        alert("Please select a valid destination path.");
        isCopying = false; // Reset the flag
        return;
    }

    const basePath = "<?php echo $uploads_folder; ?>";
    const destinationPath = basePath + selectedOption.value;
    const currentFolder = "<?php echo $current_folder_path; ?>";
    const sourcePath = currentFolder + itemName;

    console.log("Source Path:", sourcePath); // Debugging
    console.log("Destination Path:", destinationPath); // Debugging

    if (confirm(`Are you sure you want to copy ${itemName} to ${destinationPath}?`)) {
        const formData = new FormData();
        formData.append('copy_item', true); // New key for copy operation
        formData.append('item', itemName);
        formData.append('sourcePath', sourcePath);
        formData.append('destination', destinationPath);
        formData.append('isFolder', isFolder);

        fetch('principal_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Copied successfully");
                // Refresh notifications if visible
                if (document.getElementById('notifications-container').style.display === 'block') {
                    showNotifications();
                }
                // No page reload needed since original remains
            } else {
                alert(data.message);
            }
            isCopying = false; // Reset the flag
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
            isCopying = false; // Reset the flag
        });
    } else {
        isCopying = false; // Reset the flag
    }
}
// Function to clear all notifications
// Function to clear all notifications
// Function to clear all notifications
function clearAllNotifications() {
    if (confirm("Are you sure you want to clear all notifications?")) {
        const formData = new FormData();
        formData.append('clear_all_notifications', true);

        fetch('principal_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url; // Redirect to refresh the page
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while clearing notifications.');
        });
    }
}
     // Function to move an item (file or folder)
     let isMoving = false; // Flag to prevent multiple calls

function moveItem(itemName, isFolder) {
    if (isMoving) return; // Prevent multiple calls
    isMoving = true;

    const destinationPathDropdown = document.getElementById('destinationPath');
    const selectedOption = destinationPathDropdown.options[destinationPathDropdown.selectedIndex];

    if (!destinationPathDropdown || !selectedOption || selectedOption.value === "") {
        alert("Please select a valid destination path.");
        isMoving = false; // Reset the flag
        return;
    }

    const basePath = "<?php echo $uploads_folder; ?>";
    const destinationPath = basePath + selectedOption.value;
    const currentFolder = "<?php echo $current_folder_path; ?>";
    const sourcePath = currentFolder + itemName;

    console.log("Source Path:", sourcePath); // Debugging
    console.log("Destination Path:", destinationPath); // Debugging

    if (confirm(`Are you sure you want to move ${itemName} to ${destinationPath}?`)) {
        const formData = new FormData();
        formData.append('move_item', true);
        formData.append('item', itemName);
        formData.append('sourcePath', sourcePath);
        formData.append('destination', destinationPath);
        formData.append('isFolder', isFolder);

        fetch('principal_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Moved successfully");
                window.location.reload();
            } else {
                alert(data.message);
            }
            isMoving = false; // Reset the flag
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
            isMoving = false; // Reset the flag
        });
    } else {
        isMoving = false; // Reset the flag
    }
}

// Attach event listeners after the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    const moveButtons = document.querySelectorAll('.folder-item button, .file-item button');
    moveButtons.forEach(button => {
        if (button.textContent === 'Move') {
            // Remove any existing event listeners to prevent duplication
            button.removeEventListener('click', moveItemHandler);
            // Add the event listener
            button.addEventListener('click', moveItemHandler);
        }
    });
});

// Handler for the move button click event
function moveItemHandler(event) {
    console.log("Move button clicked"); // Debugging
    event.stopPropagation(); // Prevent the event from bubbling up
    const itemName = this.closest('.folder-item, .file-item').querySelector('a').textContent.trim();
    const isFolder = this.closest('.folder-item') !== null;
    moveItem(itemName, isFolder);
}
         // Function to show the Home section (Files and Folders)
         function showHome() {
            document.getElementById('principal-section').style.display = 'none';
            document.getElementById('hod-section').style.display = 'none';
            document.getElementById('files-folders-section').style.display = 'block';
            document.getElementById('notifications-container').style.display = 'none';
            document.getElementById('breadcrumb-container').style.display = 'block'; // Show the breadcrumb
            sessionStorage.removeItem('activeSection');
        }
        // Function to show the Principal Section
        function showPrincipalSection() {
            document.getElementById('principal-section').style.display = 'block';
            document.getElementById('hod-section').style.display = 'none';
            document.getElementById('files-folders-section').style.display = 'block';
            document.getElementById('notifications-container').style.display = 'none';
            document.getElementById('breadcrumb-container').style.display = 'block'; // Show the breadcrumb
            sessionStorage.setItem('activeSection', 'principal');
        }

        // Function to show the HOD Section
        function showHODSection() {
            document.getElementById('principal-section').style.display = 'none';
            document.getElementById('hod-section').style.display = 'block';
            document.getElementById('files-folders-section').style.display = 'block';
            document.getElementById('notifications-container').style.display = 'none';
            document.getElementById('breadcrumb-container').style.display = 'block'; // Show the breadcrumb
            sessionStorage.setItem('activeSection', 'hod');
        }

        // Function to show the Notifications Section
        function showNotifications() {
            document.getElementById('principal-section').style.display = 'none';
            document.getElementById('hod-section').style.display = 'none';
            document.getElementById('files-folders-section').style.display = 'none';
            document.getElementById('notifications-container').style.display = 'block';
            document.getElementById('breadcrumb-container').style.display = 'none'; // Hide the breadcrumb
            sessionStorage.setItem('activeSection', 'notifications');
        }

        // Show Home by default on page load
    document.addEventListener('DOMContentLoaded', function () {
        const activeSection = sessionStorage.getItem('activeSection');
    if (activeSection === 'principal') {
        showPrincipalSection();
    } else if (activeSection === 'hod') {
        showHODSection();
    } else if (activeSection === 'notifications') {
        showNotifications();
    } else {
        showHome(); // Default to Home if no section is active
    }
});
        // Function to delete a notification
        function deleteNotification(notificationContent) {
            if (confirm("Are you sure you want to delete this notification?")) {
                window.location.href = "principal_dashboard.php?delete_notification=" + encodeURIComponent(notificationContent);
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Choices.js for all select elements
            const selectElements = document.querySelectorAll('select');
            selectElements.forEach((select) => {
                new Choices(select, {
                    searchEnabled: true, // Enable search functionality
                    shouldSort: false,   // Disable sorting (optional)
                    placeholder: true,   // Keep the placeholder
                    searchPlaceholderValue: 'Search...', // Custom search placeholder
                });
            });
        });

        // Function to delete a file
// Function to delete a file
function deleteFile(fileName) {
    if (confirm("Are you sure you want to delete this file?")) {
        const currentFolder = "<?php echo $current_folder; ?>";
        window.location.href = "delete_file.php?file=" + encodeURIComponent(fileName) + "&current_folder=" + encodeURIComponent(currentFolder);
    }
}

// Function to delete a folder
function deleteFolder(folderName) {
    if (confirm("Are you sure you want to delete this folder and all its contents?")) {
        const currentFolder = "<?php echo $current_folder; ?>";
        window.location.href = "delete_file.php?folder=" + encodeURIComponent(folderName) + "&current_folder=" + encodeURIComponent(currentFolder);
    }
}

        // Function to select all options in a dropdown
        function selectAllOptions(button) {
            const selectElement = button.closest('.dropdown-wrapper').querySelector('select');
            if (selectElement) {
                Array.from(selectElement.options).forEach(option => {
                    option.selected = true;
                });
                if (selectElement._choices) {
                    selectElement._choices.setChoices(selectElement._choices.config.choices);
                }
            }
        }

         // Function to filter notifications
         function filterNotifications() {
            const searchInput = document.getElementById('notificationSearch').value.toLowerCase();
            const notificationItems = document.querySelectorAll('.notification-item');

            notificationItems.forEach((item) => {
                const notificationText = item.querySelector('span').textContent.toLowerCase();
                if (notificationText.includes(searchInput)) {
                    item.style.display = 'flex'; // Show matching notifications
                } else {
                    item.style.display = 'none'; // Hide non-matching notifications
                }
            });
        }

        // Attach event listener to the search input
        document.getElementById('notificationSearch').addEventListener('input', filterNotifications);
    </script>
</body>
</html>