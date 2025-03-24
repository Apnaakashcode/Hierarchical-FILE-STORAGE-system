<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
// Check if the user is logged in and is an HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: login.php");
    exit();
}

$branch = $_SESSION['branch'];
$hod_username = $_SESSION['username'];

// Database connection
$servername = "localhost";
$db_username = "root";
$db_password = "Root@123";
$dbname = "admin_panel";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize the $message variable to avoid undefined variable warnings
$message = "";
// Function to add a notification
function addNotification($message) {
    $notificationFile = 'notifications.txt';
    $timestamp = date('Y-m-d H:i:s'); // This will now use Indian time
    $message = str_replace('uploads/principal/HOD/', '', $message);
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
    header("Location: hod_dashboard.php"); // Refresh the page
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
// Function to clear all notifications
function clearAllNotifications() {
    $notificationFile = 'notifications.txt';
    if (file_exists($notificationFile)) {
        // Clear the file by writing an empty string
        file_put_contents($notificationFile, '');
    }
}

// Handle the clear all action
if (isset($_GET['clear_all_notifications'])) {
    clearAllNotifications();
    header("Location: hod_dashboard.php"); // Refresh the page
    exit();
}
// Fetch the principal's username
$principal_sql = "SELECT username FROM users WHERE role = 'principal'";
$principal_result = $conn->query($principal_sql);

if ($principal_result->num_rows > 0) {
    $principal_row = $principal_result->fetch_assoc();
    $principal_username = $principal_row['username'];

    // HOD folder path
    $hod_folder = "uploads/$principal_username/HOD/$hod_username/";

    // Ensure HOD folder exists
    if (!is_dir($hod_folder)) {
        mkdir($hod_folder, 0777, true);
    }

    // Personal folder path for HOD
    $personal_folder = $hod_folder . "MY_UPLOADS/";
    if (!is_dir($personal_folder)) {
        mkdir($personal_folder, 0777, true);
    }

    // Faculty folder path
    $faculty_folder = $hod_folder . "FACULTY/";
    if (!is_dir($faculty_folder)) {
        mkdir($faculty_folder, 0777, true);
    }

    // Fetch all faculty usernames
    $faculty_sql = "SELECT username FROM users WHERE role = 'faculty' AND branch = '$branch'";
    $faculty_result = $conn->query($faculty_sql);
    $faculty_list = [];
    while ($faculty_row = $faculty_result->fetch_assoc()) {
        $faculty_list[] = $faculty_row['username'];
    }
} else {
    die("Error: No principal found.");
}

// Handle the get_all_paths action
if (isset($_GET['action']) && $_GET['action'] === 'get_all_paths') {
    // Generate paths for HOD section
    $all_paths = generateAllFolderPaths($hod_folder, '', true); // Reset unique paths

    // Generate paths for Faculty section
    foreach ($faculty_list as $faculty) {
        $faculty_uploads_path = $faculty_folder . $faculty . '/HOD_UPLOADS/';
        $all_paths .= generateAllFolderPaths($faculty_uploads_path, $faculty . '/HOD_UPLOADS/');
    }

    // Output the options
    echo $all_paths;
    exit();
}


// Handle folder navigation
$current_folder = $hod_folder;
if (isset($_GET['folder'])) {
    $folder_path = $_GET['folder'];
    $current_folder = $hod_folder . $folder_path . '/';

    // Ensure the folder exists
    if (!is_dir($current_folder)) {
        die("Error: Folder does not exist.");
    }
}

// Function to recursively list all folders and subfolders
function listFoldersRecursively($dir) {
    $folders = [];
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $item_path = $dir . $item;
        if (is_dir($item_path)) {
            $folders[] = $item_path;
            $folders = array_merge($folders, listFoldersRecursively($item_path . '/'));
        }
    }
    return $folders;
}




// Function to generate initial folder options for HOD
// Function to generate initial folder options for HOD
function generateInitialHODFolderOptions($hod_folder) {
    $options = '';
    $initial_folders = ['MY_UPLOADS', 'PRINCIPAL_UPLOADS']; // Add other initial folders if needed

    foreach ($initial_folders as $folder) {
        $folder_path = $hod_folder . $folder . '/';
        if (is_dir($folder_path)) {
            $options .= "<option value='$folder/'>$folder</option>";
        }
    }
    return $options;
}

// Function to recursively list all subfolders with full hierarchical paths
function generateHODSubfolderOptions($hod_folder) {
    $options = '';
    $initial_folders = ['MY_UPLOADS', 'PRINCIPAL_UPLOADS'];

    foreach ($initial_folders as $folder) {
        $folder_path = $hod_folder . $folder . '/';
        if (is_dir($folder_path)) {
            // Add the main folder option
            $options .= "<option value='$folder/'>$folder</option>";

            // Recursively add subfolders
            $items = array_diff(scandir($folder_path), ['.', '..']);
            foreach ($items as $item) {
                $item_path = $folder_path . $item;
                if (is_dir($item_path)) {
                    $relative_path = $folder . '/' . $item . '/';
                    $options .= "<option value='$relative_path'>$relative_path</option>";
                    $options .= generateHODSubfolderOptionsRecursive($item_path . '/', $relative_path);
                }
            }
        }
    }
    return $options;
}

function generateHODSubfolderOptionsRecursive($base_path, $prefix = '') {
    $options = '';
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                $relative_path = $prefix . $item . '/';
                $options .= "<option value='$relative_path'>$relative_path</option>";
                $options .= generateHODSubfolderOptionsRecursive($item_path . '/', $relative_path);
            }
        }
    }
    return $options;
}
// Function to generate faculty main folder options
// Function to generate faculty main folder options
function generateFacultyMainFolderOptions($faculty_folder, $faculty_list) {
    $options = '';
    foreach ($faculty_list as $faculty) {
        $faculty_uploads_path = $faculty_folder . $faculty . '/HOD_UPLOADS/';
        if (is_dir($faculty_uploads_path)) {
            // Display faculty username and HOD_UPLOADS folder
            $options .= "<option value='$faculty'>$faculty</option>";
        }
    }
    return $options;
}
// Function to generate faculty subfolder options
function generateFacultySubfolderOptions($faculty_folder, $faculty_list) {
    $options = '';
    foreach ($faculty_list as $faculty) {
        $faculty_uploads_path = $faculty_folder . $faculty . '/HOD_UPLOADS/';
        if (is_dir($faculty_uploads_path)) {
            // Recursively list folders under HOD_UPLOADS
            $options .= generateFolderOptionsRecursive($faculty_uploads_path, $faculty . '/HOD_UPLOADS/');
        }
    }
    return $options;
}

// Helper function to recursively list folders
function generateFolderOptionsRecursive($base_path, $prefix = '') {
    $options = '';
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                // Include the full path in the option text and value
                $options .= "<option value='$prefix$item/'>$prefix$item/</option>";
                // Recursively add subfolders
                $options .= generateFolderOptionsRecursive($item_path . '/', $prefix . $item . '/');
            }
        }
    }
    return $options;
}

// Fetch list of files and folders in the current directory
$files = [];
$folders = [];
if (is_dir($current_folder)) {
    $items = array_diff(scandir($current_folder), ['.', '..']);
    foreach ($items as $item) {
        $item_path = $current_folder . $item;
        if (is_dir($item_path)) {
            $folders[] = $item;
        } elseif (is_file($item_path)) {
            $files[] = $item;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_main_folder'])) {
    $selected_folder = $_POST['main_folder_name'];
    $folder_name = trim($_POST['new_folder_name']);

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $new_folder_path = $hod_folder . $selected_folder . '/' . $folder_name . '/';

            if (!is_dir($new_folder_path)) {
                if (!mkdir($new_folder_path, 0777, true)) {
                    $message = "Failed to create folder.";
                } else {
                    $message = "Main folder created successfully.";
                     // Add notification
                     addNotification("New main folder '$folder_name' created in HOD section.");
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
// Handle subfolder creation for HOD (inside personal folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sub_folder_hod'])) {
    $parent_folders = $_POST['parent_folder']; // Array of selected parent folders
    $folder_name = trim($_POST['sub_folder_name']);

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($parent_folders as $parent_folder) {
                // Construct the full path based on the selected parent folder
                $new_folder_path = $hod_folder . $parent_folder . $folder_name . '/';

                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                        // Add notification
                        addNotification("New subfolder '$folder_name' created in HOD section under '$parent_folder'.");
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

// Handle folder creation for Faculty (inside faculty folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_faculty_folder'])) {
    $faculty_usernames = $_POST['faculty_username']; // Array of selected faculty usernames
    $folder_name = trim($_POST['faculty_folder_name']);

    if (!empty($folder_name)) {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($faculty_usernames as $faculty_username) {
                // Construct the correct path directly inside HOD_UPLOADS
                $new_folder_path = $faculty_folder . $faculty_username . '/HOD_UPLOADS/' . $folder_name . '/';

                // Debug: Check the folder path
               // echo "Creating folder at: $new_folder_path<br>";

                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                         // Add notification
                         addNotification("New folder '$folder_name' created for $faculty_username in FACULTY.");
                    }
                }
                else {
                    $error_count++;
                }
            }

            if ($success_count > 0) {
                $message = "Folder created successfully for $success_count faculty member(s).";
            }
            if ($error_count > 0) {
                $message .= " Failed to create folder for $error_count faculty member(s).";
            }
        } else {
            $message = "Invalid folder name.";
        }
    } else {
        $message = "Folder name cannot be empty.";
    }
}
// Handle file upload for HOD folder (inside personal folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hod_upload'])) {
    $selected_folders = $_POST['hod_folder']; // Array of selected folders
    $upload_files = $_FILES['hod_file'];

    if (!empty($selected_folders) && !empty($upload_files['name'][0])) {
        $success_count = 0;
        $error_count = 0;

        // Loop through each file
        foreach ($upload_files['tmp_name'] as $index => $tmp_name) {
            $file_name = basename($upload_files['name'][$index]);

            // Loop through each selected folder
            foreach ($selected_folders as $selected_folder) {
                // Construct the upload path based on the selected folder
                $upload_path = $hod_folder . $selected_folder;

                // Ensure the folder exists
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $file_path = $upload_path . $file_name;

                // Copy the file instead of moving it
                if (copy($tmp_name, $file_path)) {
                    $success_count++;
                     // Add notification
                     addNotification("New file '$file_name' uploaded to HOD section in '$selected_folder'.");
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
// Handle subfolder creation for Faculty (inside faculty folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sub_folder_faculty'])) {
    $parent_folders = $_POST['parent_folder']; // Array of selected parent folders
    $folder_name = trim($_POST['sub_folder_name']);

    if (!empty($folder_name)) {
        // Sanitize folder name
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder_name);
        if (!empty($folder_name)) {
            $success_count = 0;
            $error_count = 0;

            foreach ($parent_folders as $parent_folder) {
                // Construct the new folder path
                $new_folder_path = $faculty_folder . $parent_folder . '/' . $folder_name . '/';

                // Debug: Check the folder path
               // echo "Creating folder at: $new_folder_path<br>";

                if (!is_dir($new_folder_path)) {
                    if (!mkdir($new_folder_path, 0777, true)) {
                        $error_count++;
                    } else {
                        $success_count++;
                         // Add notification for subfolder creation
                         addNotification("New subfolder '$folder_name' created in $parent_folder for faculty.");
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
// Handle file upload for Faculty folders (inside faculty folder)
// Handle file upload for Faculty folders (inside faculty folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['faculty_folder'])) {
    $faculty_folders = $_POST['faculty_folder']; // Array of selected faculty folders
    $upload_files = $_FILES['file'];

    if (!empty($faculty_folders) && !empty($upload_files['name'][0])) {
        $success_count = 0;
        $error_count = 0;

        // Loop through each file
        foreach ($upload_files['tmp_name'] as $index => $tmp_name) {
            $file_name = basename($upload_files['name'][$index]);

            // Loop through each selected folder
            foreach ($faculty_folders as $faculty_folder_name) {
                $faculty_folder_path = $faculty_folder . $faculty_folder_name . '/';

                // Ensure the folder exists
                if (!is_dir($faculty_folder_path)) {
                    mkdir($faculty_folder_path, 0777, true);
                }

                $file_path = $faculty_folder_path . $file_name;

                // Copy the file instead of moving it
                if (copy($tmp_name, $file_path)) {
                    $success_count++;

                       // Add notification
                    addNotification("New file '$file_name' uploaded to $faculty_folder_name in FACULTY.");
                      }
                else {
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

// Function to collect all folder paths recursively
function collectAllFolderPaths($base_path, $prefix = '', &$paths = []) {
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                $relative_path = $prefix . $item . '/';
                // Only include paths under MY_UPLOADS, PRINCIPAL_UPLOADS, or FACULTY
                if (strpos($relative_path, 'MY_UPLOADS/') === 0 || 
                    strpos($relative_path, 'PRINCIPAL_UPLOADS/') === 0 || 
                    strpos($relative_path, 'FACULTY/') === 0) {
                    $paths[] = $relative_path;
                    collectAllFolderPaths($item_path . '/', $relative_path, $paths);
                }
            }
        }
    }
    return $paths;
}
// Function to recursively list all folders and subfolders with full paths
// Function to recursively list all folders and subfolders with full paths
function generateAllFolderPaths($base_path, $prefix = '', $reset = false) {
    static $unique_paths = []; // Static variable to store unique paths

    // Reset the unique paths array if requested
    if ($reset) {
        $unique_paths = [];
    }

    $options = '';
    if (is_dir($base_path)) {
        $items = array_diff(scandir($base_path), ['.', '..']);
        foreach ($items as $item) {
            $item_path = $base_path . $item;
            if (is_dir($item_path)) {
                $relative_path = $prefix . $item . '/';

                // Check if the path starts with FACULTY/, MY_UPLOADS/, or PRINCIPAL_UPLOADS/
                if (strpos($relative_path, 'FACULTY/') === 0 || 
                    strpos($relative_path, 'MY_UPLOADS/') === 0 || 
                    strpos($relative_path, 'PRINCIPAL_UPLOADS/') === 0) {

                    if (!isset($unique_paths[$relative_path])) {
                        $unique_paths[$relative_path] = true; // Mark this path as added

                        // Display relative path in the dropdown
                        $options .= "<option value='$relative_path'>$relative_path</option>";
                        $options .= generateAllFolderPaths($item_path . '/', $relative_path);
                    }
                }
            }
        }
    }
    return $options;
}
// Handle file deletion
if (isset($_GET['delete']) && isset($_GET['folder'])) {
    $folder_path = $_GET['folder'];
    $file_to_delete = $hod_folder . $folder_path . '/' . basename($_GET['delete']);

    if (file_exists($file_to_delete) && is_file($file_to_delete)) {
        if (unlink($file_to_delete)) {
            $message = "File deleted successfully.";
        } else {
            $message = "Failed to delete file.";
        }
    } else {
        $message = "File not found.";
    }
    header("Location: hod_dashboard.php?folder=" . urlencode($folder_path));
    exit();
}

// Handle folder deletion
if (isset($_GET['delete_folder']) && isset($_GET['folder'])) {
    $folder_path = $_GET['folder'];
    $folder_to_delete = $hod_folder . $folder_path . '/' . basename($_GET['delete_folder']);

    if (is_dir($folder_to_delete)) {
        // Recursively delete the folder and its contents
        function deleteFolder($folder_path) {
            if (!is_dir($folder_path)) return false;
            foreach (scandir($folder_path) as $item) {
                if ($item === '.' || $item === '..') continue;
                $item_path = $folder_path . DIRECTORY_SEPARATOR . $item;
                is_dir($item_path) ? deleteFolder($item_path) : unlink($item_path);
            }
            return rmdir($folder_path);
        }

        if (deleteFolder($folder_to_delete)) {
            $message = "Folder deleted successfully.";
        } else {
            $message = "Failed to delete folder.";
        }
    } else {
        $message = "Folder not found.";
    }
    header("Location: hod_dashboard.php?folder=" . urlencode($folder_path));
    exit();
}
// Handle copy operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_item'])) {
    $item = $_POST['item'];
    $sourcePath = $_POST['sourcePath'];
    $destination = $_POST['destination'];
    $isFolder = $_POST['isFolder'] === 'true';

    // Debug: Log paths
    error_log("Source Path: $sourcePath");
    error_log("Destination Path: $destination");

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
        $notificationMessage = $isFolder 
            ? "Folder '$item' copied to '$destination'." 
            : "File '$item' copied to '$destination'.";
        addNotification($notificationMessage);
        echo json_encode(['success' => true, 'message' => $isFolder ? 'Folder copied successfully.' : 'File copied successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to copy ' . ($isFolder ? 'folder.' : 'file.')]);
    }
    exit();
}

// Existing Move operation remains unchanged
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_item'])) {
    $item = $_POST['item'];
    $sourcePath = $_POST['sourcePath'];
    $destination = $_POST['destination'];
    $isFolder = $_POST['isFolder'] === 'true';

    error_log("Source Path: $sourcePath");
    error_log("Destination Path: $destination");

    if (!file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'message' => 'Source file or folder does not exist.']);
        exit();
    }

    if (!is_dir($destination)) {
        echo json_encode(['success' => false, 'message' => 'Destination path does not exist.']);
        exit();
    }

    if (rename($sourcePath, $destination . '/' . basename($item))) {
        $notificationMessage = $isFolder 
            ? "Folder '$item' moved to '$destination'." 
            : "File '$item' moved to '$destination'.";
        addNotification($notificationMessage);
        echo json_encode(['success' => true, 'message' => $isFolder ? 'Folder moved successfully.' : 'File moved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move ' . ($isFolder ? 'folder.' : 'file.')]);
    }
    exit();
}
// Handle move operation
// Handle move operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_item'])) {
    $item = $_POST['item'];
    $sourcePath = $_POST['sourcePath']; // Get the source path from the form data
    $destination = $_POST['destination'];
    $isFolder = $_POST['isFolder'] === 'true';

    // Debug: Log paths
    error_log("Source Path: $sourcePath");
    error_log("Destination Path: $destination");

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
        // Add a notification for the move operation
        $notificationMessage = $isFolder 
            ? "Folder '$item' moved to '$destination'." 
            : "File '$item' moved to '$destination'.";
        addNotification($notificationMessage);
        echo json_encode(['success' => true, 'message' => $isFolder ? 'Folder moved successfully.' : 'File moved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move ' . ($isFolder ? 'folder.' : 'file.')]);
    }
    exit();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <style>
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
/* HOD Section */
#hod-section {
    background-color: rgba(173, 216, 230, 0.9); /* Light blue background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
    margin-left: 100px; /* Adjust for sidebar */
    width: calc(100% - 240px); /* Adjust width */
}

/* Faculty Section */
#faculty-section {
    background-color: rgba(255, 182, 193, 0.9); /* Light pink background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
    margin-left: 100px; /* Adjust for sidebar */
    width: calc(100% - 240px); /* Adjust width */
}

/* Hide Sections by Default */
#hod-section,
#faculty-section {
    display: none;
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
    background-color: white; /* Ensure white background for each item */
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

    

        h1, h2, h3 {
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

        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
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
            background-color: #dc3545;
    color: #fff;
    border: none;
    padding: 8px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    margin-left: 20px; /* Increased margin to push buttons further to the right */
    transition: background-color 0.3s ease;
        }
/* Specific styles for the delete button */
.file-item button:first-of-type,
.folder-item button:first-of-type {
    margin-left: 150px; /* Push the delete button further to the right */
}

/* Specific styles for the move button */
.file-item button:last-of-type,
.folder-item button:last-of-type {
    background-color:green;
    margin-left: 10px; /* Adjust the move button's position */
}


        .file-item button:hover, .folder-item button:hover {
            background-color: #c82333;
        }

        .no-files, .no-folders {
            text-align: center;
    color: #777;
    font-size: 14px;
    margin-top: 20px;
        }

        .folder-item.selected {
            background-color: #e0f7fa;
            border-color: #007bff;
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

        .container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        /* Updated CSS for Left and Right Panels */
.left-panel,
.right-panel {
    background-color:light yellow; /* Slightly more opaque */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px; /* Add space below panels */
}
        .left-panel {
            margin-right: 10px; /* Add space between left and right panels */
}

/* Right Panel Styling */
.right-panel {
    margin-left: 10px; /* Add space between left and right panels */
}

        .files-folders-section {
            margin-top: 20px;
    width: 40vw;
    margin-left: 50px;
    background-color: lightyellow; /* Semi-transparent white */
    padding: 20px;
    border-radius: 8px solid black;
    box-shadow: 0 2px 4px rgba(55, 174, 47, 0.05);
    position: relative; /* Ensure the section is a positioning context */
    z-index: 1; /* Ensure it stays above other elements */
        }
#destinationPath {
    width: 100%;
    max-width: 500px; /* Increased width */
    padding: 12px; /* Increased padding */
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #fff;
    font-size: 16px; /* Increased font size */
    transition: border-color 0.3s ease;
    margin-bottom: 15px;
    z-index: 1; /* Ensure dropdown is above other elements */
}

/* Ensure the dropdown does not overlap with other elements */
#destinationPath:focus {
    border-color: #007bff;
    outline: none;
    z-index: 2; /* Bring dropdown to the front when focused */
}
        /* Horizontal line styling */
hr {
    border: 0;
    height: 1px;
    background: #ddd;
    margin: 20px 0;
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
#faculty-section .dropdown-wrapper select {
    min-width: 400px; /* Increase minimum width for larger dropdowns */
}

/* Ensure the dropdowns in the HOD and Faculty sections expand */
#hod-section .dropdown-wrapper,
#faculty-section .dropdown-wrapper {
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
/* File Input Styling */
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
    position: relative; /* Ensure the dropdown is positioned correctly */
    z-index: 1; /* Ensure it stays above other elements */
}

.choices__inner {
    min-height: 50px; /* Increase minimum height */
    padding: 12px; /* Increase padding */
    border: 1px solid #ddd; /* Add border */
    border-radius: 5px; /* Add border radius */
    background-color: #fff; /* Set background color */
    font-size: 16px; /* Increase font size */
    transition: border-color 0.3s ease;
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
    font-size: 16px; /* Increase font size for dropdown items */
    max-height: 200px; /* Limit the height of the dropdown */
    overflow-y: auto; /* Add scrollbar if content overflows */
    position: absolute; /* Position the dropdown absolutely */
    top: 100%; /* Position below the select element */
    left: 0; /* Align with the left edge of the select element */
    width: 100%; /* Match the width of the select element */
    background-color: #fff; /* Ensure the background is white */
}
.choices__list--dropdown .choices__item {
    padding: 10px; /* Increase padding for dropdown items */
    font-size: 16px; /* Increase font size for dropdown items */
}
/* Breadcrumb Navigation Styling */
/* Breadcrumb Navigation Styling */
.breadcrumb {
    background-color: #fff; /* White background */
    padding: 15px; /* Add padding */
    border-radius: 8px; /* Rounded corners */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Subtle shadow */
    margin-bottom: 20px; /* Space below the breadcrumb */
    font-size: 18px; /* Font size */
    margin-left: 30px; /* Adjust margin */
    color: #555; /* Text color */
}

.breadcrumb a {
    color: blue; /* Blue link color */
    text-decoration: none; /* Remove underline */
    transition: color 0.3s ease; /* Smooth color transition */
}

.breadcrumb a:hover {
    color: #0056b3; /* Darker blue on hover */
    text-decoration: underline; /* Underline on hover */
}

.breadcrumb .separator {
    margin: 0 8px; /* Space between links and separators */
    color: #777; /* Separator color */
}
.clear-all-button {
    background-color: #dc3545; /* Red background */
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 15px;
    transition: background-color 0.3s ease;
}

.clear-all-button:hover {
    background-color: #c82333; /* Darker red on hover */
}
/* Reduce height of native select elements in HOD and Faculty sections */
#hod-section select,
#faculty-section select {
    min-height: 40px; /* Reduced from default */
    padding: 8px; /* Reduced padding */
    font-size: 14px; /* Slightly smaller font size */
}
/* Reduce height of Choices.js dropdowns in HOD and Faculty sections */
#hod-section .choices__inner,
#faculty-section .choices__inner {
    min-height: 40px; /* Reduced from 50px */
    padding: 8px; /* Reduced from 12px */
    font-size: 14px; /* Slightly smaller font size */
}
/* Adjust the height of selected items in Choices.js */
#hod-section .choices__list--multiple .choices__item,
#faculty-section .choices__list--multiple .choices__item {
    padding: 4px 10px; /* Reduced padding */
    font-size: 14px; /* Slightly smaller font size */
}
/* Ensure dropdown items are compact */
#hod-section .choices__list--dropdown .choices__item,
#faculty-section .choices__list--dropdown .choices__item {
    padding: 8px; /* Reduced from 10px */
    font-size: 14px; /* Slightly smaller font size */
}
/* Increase gap between containers in HOD Section */
#hod-section .form-group {
    margin-bottom: 60px; /* Increased from 25px to 40px for more gap */
}

/* Increase gap between containers in Faculty Section */
#faculty-section .form-group {
    margin-bottom: 60px; /* Increased from 25px to 40px for more gap */
}
/* Breadcrumb Search Input */
.breadcrumb .dropdown-search {
    width: 100%;
    max-width: 300px;
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

/* Highlight matching terms */
.breadcrumb .highlight {
    background-color: #007bff;
    color: white;
    padding: 2px 5px;
    border-radius: 3px;
}

/* Ensure breadcrumb links wrap properly */
#breadcrumbLinks {
    word-wrap: break-word;
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
        <li><a href="#" onclick="showHODSection()">👨‍🏫 HOD Section</a></li>
        <li><a href="#" onclick="showFacultySection()">👩‍🏫 Faculty Section</a></li>
           <li><a href="#" onclick="showNotifications()">📜 History</a></li> <!-- New Notification Option -->
    </ul>
</div>
<div class="dashboard-container">
<a href="logout.php" class="home-button">🚪 Logout</a>
        <h2  class="dashboard-heading">HOD Dashboard - <?php echo $branch; ?></h2>
<!-- Breadcrumb Navigation -->
<!-- Breadcrumb Navigation -->
<!-- Breadcrumb Navigation -->
<div class="breadcrumb">
    <input type="text" id="breadcrumbSearch" placeholder="Search folder path..." class="dropdown-search">
    <div id="breadcrumbLinks">
        <?php
        // Start with the Home link
        echo '<a href="hod_dashboard.php">Home</a>';

        // Get the current folder path from the URL
        $current_folder_path = isset($_GET['folder']) ? $_GET['folder'] : '';

        // Split the folder path into segments
        $folder_segments = explode('/', $current_folder_path);

        // Initialize the breadcrumb path
        $breadcrumb_path = '';

        // Loop through each folder segment and create breadcrumb links
        foreach ($folder_segments as $segment) {
            if (!empty($segment)) {
                $breadcrumb_path .= $segment . '/';
                echo ' <span class="separator">></span> <a href="hod_dashboard.php?folder=' . urlencode($breadcrumb_path) . '">' . htmlspecialchars($segment) . '</a>';
            }
        }
        ?>
    </div>
    <!-- Container for search result paths -->
    <div id="breadcrumbSearchResults" style="margin-top: 10px;"></div>
</div>
        <!-- Back Button -->
        <?php if (isset($_GET['folder']) && $_GET['folder'] !== ""): ?>
            <?php
            $parent_folder = dirname($_GET['folder']);
            $parent_folder = $parent_folder === '.' ? '' : $parent_folder;
            ?>
            <a href="hod_dashboard.php?folder=<?php echo urlencode($parent_folder); ?>" class="back-button">⬅ Back</a>
        <?php endif; ?>

         <!-- Notifications Section -->
<div class="notifications-container" id="notifications-container" style="display: none;">
    <h2>History</h2>
    <!-- Search Bar -->
    <input type="text" id="notificationSearch" placeholder="Search notifications..." class="dropdown-search">
     <!-- Clear All Button -->
     <button onclick="clearAllNotifications()" class="clear-all-button">Clear All</button>
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
        <!-- Side-by-side layout for HOD and Faculty sections -->
        <div class="container">
            <!-- Left Panel: HOD Section -->
            <div id="hod-section">
            <div class="left-panel">
                <h3>HOD Section</h3>
                <div class="form-group">
                <!-- Create Main Folder Form for HOD -->
                <h4>Create Main Folder (HOD)</h4>
<form action="" method="POST">
    <select name="main_folder_name" required>
        <option value="" disabled>Select main folder</option>
        <?php echo generateInitialHODFolderOptions($hod_folder); ?>
    </select>
    <input type="text" name="new_folder_name" placeholder="Enter new folder name" required>
    <button type="submit" name="create_main_folder">Create Main Folder</button>
</form>
        </div>
                <!-- Create Subfolder Form for HOD -->
                <div class="form-group">
<h4>Create Subfolder (HOD)</h4>
<form action="" method="POST">
    <div class="dropdown-wrapper">
    <select name="parent_folder[]" multiple required>
    <option value="" disabled>Select parent folders (HOD)</option>
    
                    // Pass both MY_UPLOADS and PRINCIPAL_UPLOADS paths
                    <?php echo generateHODSubfolderOptions($hod_folder); ?>
            
</select>
        <button type="button" onclick="selectAllOptions(this)">Select All</button>
    </div>
    <input type="text" name="sub_folder_name" placeholder="Enter subfolder name" required>
    <button type="submit" name="create_sub_folder_hod">Create Subfolder</button>
</form>
        </div>
               <!-- Upload File Section for HOD -->
               <div class="form-group">
<h3>Upload File (HOD Folder)</h3>
<form action="" method="POST" enctype="multipart/form-data">
    <div class="dropdown-wrapper">
        <select name="hod_folder[]" multiple required>
            <option value="" disabled>Select folders (optional)</option>
            <option value="">Upload directly to HOD folder</option>
            <?php echo generateHODSubfolderOptions($hod_folder); ?>
        </select>
        <button type="button" onclick="selectAllOptions(this)">Select All</button>
    </div>
    <input type="file" name="hod_file[]" multiple required>
    <button type="submit" name="hod_upload">Upload</button>
</form>
        </div>
        </div>
        </div>
            <!-- Right Panel: Faculty Section -->
            <div id="faculty-section">
            <div class="right-panel">
                <h3>Faculty Section</h3>
                <div class="form-group">
                <!-- Create Folder for Faculty (Multiple Selection) -->
                <h4>Create Folder for Faculty</h4>
<form action="" method="POST">
    <div class="dropdown-wrapper">
        <select name="faculty_username[]" multiple required>
            <option value="" disabled>Select faculty members</option>
            <?php echo generateFacultyMainFolderOptions($faculty_folder, $faculty_list); ?>
        </select>
        <button type="button" onclick="selectAllOptions(this)">Select All</button>
    </div>
    <input type="text" name="faculty_folder_name" placeholder="Enter folder name" required>
    <button type="submit" name="create_faculty_folder">Create Folder</button>
</form>
            </div>
                <!-- Create Subfolder Form for Faculty -->
                <div class="form-group">
                <h4>Create Subfolder (Faculty)</h4>
<form action="" method="POST">
    <div class="dropdown-wrapper">
        <select name="parent_folder[]" multiple required>
            <option value="" disabled>Select parent folders (Faculty)</option>
            <?php echo generateFacultySubfolderOptions($faculty_folder, $faculty_list); ?>
        </select>
        <button type="button" onclick="selectAllOptions(this)">Select All</button>
    </div>
    <input type="text" name="sub_folder_name" placeholder="Enter subfolder name" required>
    <button type="submit" name="create_sub_folder_faculty">Create Subfolder</button>
</form>
        </div>
                <!-- Upload File Section for Faculty -->
                <div class="form-group">
                <h4>Upload File to Faculty</h4>
<form action="" method="POST" enctype="multipart/form-data">
    <div class="dropdown-wrapper">
        <select name="faculty_folder[]" multiple required>
            <option value="" disabled>Select faculty folders</option>
            <?php echo generateFacultySubfolderOptions($faculty_folder, $faculty_list); ?>
        </select>
        <button type="button" onclick="selectAllOptions(this)">Select All</button>
    </div>
    <input type="file" name="file[]" multiple required>
    <button type="submit">Upload</button>
</form>
        </div>
        </div>
        </div>
       
          <!-- Files and Folders Section -->
<!-- Files and Folders Section -->
<div class="files-folders-section">
    <!-- Display Messages -->
    <?php if (isset($message)): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- Destination Path Dropdown -->
    <div class="form-group">
        <label for="destinationPath">Select Destination Path:</label>
        <select id="destinationPath">
            <option value="" disabled selected>Select destination</option>
            <?php
            // Generate paths for HOD section
            echo generateAllFolderPaths($hod_folder, '', true); // Reset unique paths
            // Generate paths for Faculty section
            foreach ($faculty_list as $faculty) {
                $faculty_uploads_path = $faculty_folder . $faculty . '/HOD_UPLOADS/';
                echo generateAllFolderPaths($faculty_uploads_path, $faculty . '/HOD_UPLOADS/');
            }
            ?>
        </select>
    </div>

    <!-- List of Folders -->
    <h3>Folders</h3>
    <div class="folder-list">
        <?php if (!empty($folders)): ?>
            <?php foreach ($folders as $folder): ?>
                <div class="folder-item">
                    <a href="hod_dashboard.php?folder=<?php echo urlencode(($_GET['folder'] ?? '') . '/' . $folder); ?>">📁 <?php echo htmlspecialchars($folder); ?></a>
                    <button onclick="deleteFolder('<?php echo $folder; ?>')">Delete Folder</button>
                    <button onclick="moveItem('<?php echo $folder; ?>', true)">Move</button>
                    <button onclick="copyItem('<?php echo $folder; ?>', true)" style="background-color: #28a745;">Copy</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-folders">No folders found.</p>
        <?php endif; ?>
    </div>

    <!-- List of Files -->
    <h3>Files</h3>
    <div class="file-list">
        <?php if (!empty($files)): ?>
            <?php foreach ($files as $file): ?>
                <div class="file-item">
                    <a href="<?php echo $current_folder . $file; ?>" target="_blank">📄 <?php echo $file; ?></a>
                    <button onclick="deleteFile('<?php echo $file; ?>')">Delete</button>
                    <button onclick="moveItem('<?php echo $file; ?>', false)">Move</button>
                    <button onclick="copyItem('<?php echo $file; ?>', false)" style="background-color: #28a745;">Copy</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-files">No files found.</p>
        <?php endif; ?>
    </div>
</div>
        </div>
    </div>
    <!-- Move Modal -->

    <script>
        // Function to clear all notifications
function clearAllNotifications() {
    if (confirm("Are you sure you want to clear all notifications?")) {
        // Send a request to the server to clear all notifications
        window.location.href = "hod_dashboard.php?clear_all_notifications=true";
    }
}
// Function to copy an item (file or folder)
function copyItem(itemName, isFolder) {
    const destinationPathDropdown = document.getElementById('destinationPath');
    const selectedOption = destinationPathDropdown.options[destinationPathDropdown.selectedIndex];

    // Validate the selected destination path
    if (!selectedOption || selectedOption.value === "") {
        alert("Please select a valid destination path.");
        return;
    }

    // Base path (e.g., uploads/principal/HOD/CSE-HOD/)
    const basePath = "uploads/principal/HOD/CSE-HOD/";

    // Construct the full destination path
    const destinationPath = basePath + selectedOption.value;

    // Construct the full source path
    const currentFolder = "<?php echo $current_folder; ?>";
    const sourcePath = currentFolder + itemName;

    // Confirm the copy action with the user
    if (confirm(`Are you sure you want to copy ${itemName} to ${destinationPath}?`)) {
        const formData = new FormData();
        formData.append('copy_item', true); // New key for copy operation
        formData.append('item', itemName);
        formData.append('sourcePath', sourcePath);
        formData.append('destination', destinationPath);
        formData.append('isFolder', isFolder);

        // Send the copy request to the server
        fetch('hod_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Copied successfully");
                // Refresh the notifications container if visible
                if (document.getElementById('notifications-container').style.display === 'block') {
                    showNotifications();
                }
                // No page reload needed since original remains
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }
}

// Existing moveItem function remains unchanged
function moveItem(itemName, isFolder) {
    const destinationPathDropdown = document.getElementById('destinationPath');
    const selectedOption = destinationPathDropdown.options[destinationPathDropdown.selectedIndex];

    if (!selectedOption || selectedOption.value === "") {
        alert("Please select a valid destination path.");
        return;
    }

    const basePath = "uploads/principal/HOD/CSE-HOD/";
    const destinationPath = basePath + selectedOption.value;
    const currentFolder = "<?php echo $current_folder; ?>";
    const sourcePath = currentFolder + itemName;

    if (confirm(`Are you sure you want to move ${itemName} to ${destinationPath}?`)) {
        const formData = new FormData();
        formData.append('move_item', true);
        formData.append('item', itemName);
        formData.append('sourcePath', sourcePath);
        formData.append('destination', destinationPath);
        formData.append('isFolder', isFolder);

        fetch('hod_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Moved successfully");
                if (document.getElementById('notifications-container').style.display === 'block') {
                    showNotifications();
                }
                window.location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }
}
// Function to move an item (file or folder)
function moveItem(itemName, isFolder) {
    const destinationPathDropdown = document.getElementById('destinationPath');
    const selectedOption = destinationPathDropdown.options[destinationPathDropdown.selectedIndex];

    // Validate the selected destination path
    if (!selectedOption || selectedOption.value === "") {
        alert("Please select a valid destination path.");
        return;
    }

    // Base path (e.g., uploads/principal/HOD/CSE-HOD/)
    const basePath = "uploads/principal/HOD/CSE-HOD/";

    // Construct the full destination path
    const destinationPath = basePath + selectedOption.value;

    // Construct the full source path
    const currentFolder = "<?php echo $current_folder; ?>";
    const sourcePath = currentFolder + itemName;

    // Confirm the move action with the user
    if (confirm(`Are you sure you want to move ${itemName} to ${destinationPath}?`)) {
        const formData = new FormData();
        formData.append('move_item', true);
        formData.append('item', itemName);
        formData.append('sourcePath', sourcePath); // Add sourcePath to the form data
        formData.append('destination', destinationPath);
        formData.append('isFolder', isFolder);

        // Send the move request to the server
        fetch('hod_dashboard.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json()) // Parse the response as JSON
        .then(data => {
            if (data.success) {
                alert("Moved successfully");
                 // Refresh the notifications container
                if (document.getElementById('notifications-container').style.display === 'block') {
                    showNotifications(); // Refresh notifications if the container is visible
                }
                window.location.reload(); // Refresh the page to reflect changes
            } else {
                alert(data.message); // Display the error message
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }
}

    function showHome() {
        document.getElementById('hod-section').style.display = 'none';
        document.getElementById('faculty-section').style.display = 'none';
        document.querySelector('.files-folders-section').style.display = 'block';
        document.getElementById('notifications-container').style.display = 'none';
        document.querySelector('.breadcrumb').style.display = 'block';
        sessionStorage.removeItem('activeSection');
    }

    function showHODSection() {
        document.getElementById('hod-section').style.display = 'block';
        document.getElementById('faculty-section').style.display = 'none';
        document.querySelector('.files-folders-section').style.display = 'block';
        document.getElementById('notifications-container').style.display = 'none';
        document.querySelector('.breadcrumb').style.display = 'block';
        sessionStorage.setItem('activeSection', 'hod');
    }

    function showFacultySection() {
        document.getElementById('hod-section').style.display = 'none';
        document.getElementById('faculty-section').style.display = 'block';
        document.querySelector('.files-folders-section').style.display = 'block';
        document.getElementById('notifications-container').style.display = 'none';
        document.querySelector('.breadcrumb').style.display = 'block';
        sessionStorage.setItem('activeSection', 'faculty');
    }
    function showNotifications() {
    // Hide all other sections
    document.getElementById('hod-section').style.display = 'none';
    document.getElementById('faculty-section').style.display = 'none';
    document.querySelector('.files-folders-section').style.display = 'none';
    document.querySelector('.breadcrumb').style.display = 'none';

    // Show the notifications container
    document.getElementById('notifications-container').style.display = 'block';

    // Store the active section in sessionStorage
    sessionStorage.setItem('activeSection', 'notifications');
}
    // Show Home by default on page load
    document.addEventListener('DOMContentLoaded', function () {
        const activeSection = sessionStorage.getItem('activeSection');
    if (activeSection === 'hod') {
        showHODSection();
    } else if (activeSection === 'faculty') {
        showFacultySection();
    } else if (activeSection === 'notifications') {
        showNotifications();
    } else {
        showHome(); // Default to Home if no section is active
    }
});
 document.addEventListener('DOMContentLoaded', function () {
        // Initialize Choices.js for all select elements
        const selectElements = document.querySelectorAll('select');
        selectElements.forEach((select) => {
            new Choices(select, {
                searchEnabled: true, // Enable search functionality
                shouldSort: false,   // Disable sorting (optional)
                placeholder: true,   // Keep the placeholder
                searchPlaceholderValue: 'Search...', // Custom search placeholder
                //removeItemButton: true, // Allow removing selected items
            classNames: {
                containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                item: 'choices__item',
                button: 'choices__button',
            },
            });
        });
    });

    function deleteNotification(notificationContent) {
    if (confirm("Are you sure you want to delete this notification?")) {
        // Decode the notification content to handle special characters
        const decodedContent = decodeURIComponent(notificationContent);
        // Redirect to the delete URL
        window.location.href = `hod_dashboard.php?delete_notification=${encodeURIComponent(decodedContent)}`;
    }
}
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
        // Function to delete a file
        function deleteFile(fileName) {
            if (confirm("Are you sure you want to delete this file?")) {
                window.location.href = "hod_dashboard.php?delete=" + encodeURIComponent(fileName) + "&folder=<?php echo urlencode($_GET['folder'] ?? ''); ?>";
            }
        }

        // Function to delete a folder
        function deleteFolder(folderName) {
            if (confirm("Are you sure you want to delete this folder and all its contents?")) {
                window.location.href = "hod_dashboard.php?delete_folder=" + encodeURIComponent(folderName) + "&folder=<?php echo urlencode($_GET['folder'] ?? ''); ?>";
            }
        }

        // Function to filter folders
        function filterFolders() {
            const searchInput = document.getElementById('folderSearch').value.toLowerCase();
            const folderItems = document.querySelectorAll('.folder-item');

            folderItems.forEach((item) => {
                const folderName = item.querySelector('a').textContent.toLowerCase();
                if (folderName.includes(searchInput)) {
                    item.style.display = 'flex'; // Show matching folders
                } else {
                    item.style.display = 'none'; // Hide non-matching folders
                }
            });
        }

        function selectAllOptions(button) {
    // Find the closest select element within the same wrapper
    const selectElement = button.closest('.dropdown-wrapper').querySelector('select');

    // Check if the select element exists
    if (selectElement) {
        // Select all options in the dropdown
        Array.from(selectElement.options).forEach(option => {
            option.selected = true;
        });

        // Trigger the Choices.js update (if Choices.js is used)
        if (selectElement._choices) {
            selectElement._choices.setChoices(selectElement._choices.config.choices);
        }
    }
}

// Function to collect all paths dynamically via PHP (simulated client-side)
function getAllPaths() {
    return new Promise((resolve) => {
        fetch('hod_dashboard.php?action=get_all_paths')
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
    let breadcrumbHTML = '<a href="hod_dashboard.php">Home</a>';
    let breadcrumbPath = '';
    folderSegments.forEach((segment) => {
        breadcrumbPath += segment + '/';
        breadcrumbHTML += ` <span class="separator">></span> <a href="hod_dashboard.php?folder=${encodeURIComponent(breadcrumbPath)}">${htmlspecialchars(segment)}</a>`;
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
                searchResultsHTML += `<a href="hod_dashboard.php?folder=${encodeURIComponent(path)}">${highlightedPath}</a>`;
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
        // Function to filter files
        function filterFiles() {
            const searchInput = document.getElementById('fileSearch').value.toLowerCase();
            const fileItems = document.querySelectorAll('.file-item');

            fileItems.forEach((item) => {
                const fileName = item.querySelector('a').textContent.toLowerCase();
                if (fileName.includes(searchInput)) {
                    item.style.display = 'flex'; // Show matching files
                } else {
                    item.style.display = 'none'; // Hide non-matching files
                }
            });
        }

        // Attach event listeners to search inputs
        document.getElementById('folderSearch').addEventListener('input', filterFolders);
        document.getElementById('fileSearch').addEventListener('input', filterFiles);
    </script>
</body>
</html>