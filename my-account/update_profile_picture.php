<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'You must be logged in to update your profile picture.';
    header("Location: ../login/login.php");
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid security token. Please try again.';
    header("Location: my-account.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database connection failed.';
    header("Location: my-account.php");
    exit();
}

// Confirm POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header("Location: my-account.php");
    exit();
}

// Confirm file was uploaded
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] === UPLOAD_ERR_NO_FILE) {
    $_SESSION['error_message'] = 'No file was selected.';
    header("Location: my-account.php");
    exit();
}

// Check for upload errors
if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
    ];
    
    $error = $errorMessages[$_FILES['profile_pic']['error']] ?? 'Unknown upload error.';
    $_SESSION['error_message'] = $error;
    header("Location: my-account.php");
    exit();
}

// File validation
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

$fileSize = $_FILES['profile_pic']['size'];
$fileName = $_FILES['profile_pic']['name'];
$fileTmpName = $_FILES['profile_pic']['tmp_name'];
$fileMimeType = mime_content_type($fileTmpName);
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file size
if ($fileSize > $maxFileSize) {
    $_SESSION['error_message'] = 'File is too large. Maximum size is 5MB.';
    header("Location: my-account.php");
    exit();
}

// Validate file extension
if (!in_array($fileExtension, $allowedExtensions)) {
    $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
    header("Location: my-account.php");
    exit();
}

// Validate MIME type
if (!in_array($fileMimeType, $allowedMimeTypes)) {
    $_SESSION['error_message'] = 'Invalid file format detected.';
    header("Location: my-account.php");
    exit();
}

// Validate that it's actually an image
$imageInfo = getimagesize($fileTmpName);
if ($imageInfo === false) {
    $_SESSION['error_message'] = 'File is not a valid image.';
    header("Location: my-account.php");
    exit();
}

// FIXED: Use consistent absolute path from document root
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Subdivision-Appointment-System/uploads/profile_pictures/';
$publicPath = 'uploads/profile_pictures/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $_SESSION['error_message'] = 'Failed to create upload directory.';
        header("Location: my-account.php");
        exit();
    }
}

// Create .htaccess to prevent PHP execution in upload directory (security)
$htaccessPath = $uploadDir . '.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = "php_flag engine off\nOptions -Indexes";
    file_put_contents($htaccessPath, $htaccessContent);
}

// Get old profile picture
try {
    $stmt = $conn->prepare("SELECT ProfilePictureURL FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $oldPic = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to retrieve current profile picture.';
    header("Location: my-account.php");
    exit();
}

// Generate unique filename
$newFileName = 'profile_' . $_SESSION['user_id'] . '_' . uniqid() . '_' . time() . '.' . $fileExtension;
$fullPath = $uploadDir . $newFileName;
$dbPath = $publicPath . $newFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpName, $fullPath)) {
    $_SESSION['error_message'] = 'Failed to save the uploaded file.';
    header("Location: my-account.php");
    exit();
}

// Set proper permissions
chmod($fullPath, 0644);

// Update database
try {
    $stmt = $conn->prepare("
        UPDATE users
        SET ProfilePictureURL = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$dbPath, $_SESSION['user_id']]);
    
    // Delete old profile picture if it exists and is not the default
    if ($oldPic && 
        !empty($oldPic) && 
        strpos($oldPic, 'default-profile.png') === false) {
        
        // Handle both absolute and relative paths
        if (strpos($oldPic, '/Subdivision-Appointment-System/') === 0) {
            $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . $oldPic;
        } else {
            $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . '/Subdivision-Appointment-System/' . $oldPic;
        }
        
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
    
    $_SESSION['success_message'] = 'Profile picture updated successfully!';
    
} catch (PDOException $e) {
    // If database update fails, delete the uploaded file
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    $_SESSION['error_message'] = 'Failed to update profile picture in database.';
    header("Location: my-account.php");
    exit();
}

// Redirect back to account page
header("Location: my-account.php");
exit();