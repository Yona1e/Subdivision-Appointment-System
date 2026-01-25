<?php
session_start();
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in.'
    ]);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
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
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.'
    ]);
    exit();
}

// Get POST data
$oldPassword     = $_POST['old_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Basic validation
if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'All fields are required.'
    ]);
    exit();
}

if (strlen($newPassword) < 6) {
    echo json_encode([
        'status' => 'error',
        'message' => 'New password must be at least 6 characters.'
    ]);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode([
        'status' => 'error',
        'message' => 'New password and confirmation do not match.'
    ]);
    exit();
}

// Fetch current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentHash = $stmt->fetchColumn();

if (!$currentHash) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not found.'
    ]);
    exit();
}

// Verify old password
if (!password_verify($oldPassword, $currentHash)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Old password is incorrect.'
    ]);
    exit();
}

// ❌ PREVENT REUSING THE SAME PASSWORD
if (password_verify($newPassword, $currentHash)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'New password cannot be the same as your old password.'
    ]);
    exit();
}

// Hash new password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
if ($stmt->execute([$newHash, $_SESSION['user_id']])) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Password updated successfully.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update password.'
    ]);
}

exit();
