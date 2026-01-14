<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in.']);
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
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Confirm POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Get POST data
$oldPassword = $_POST['old_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate new password
if (strlen($newPassword) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'New password must be at least 6 characters.']);
    exit();
}
if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'New password and confirmation do not match.']);
    exit();
}

// Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentHash = $stmt->fetchColumn();

// Verify old password
if (!password_verify($oldPassword, $currentHash)) {
    echo json_encode(['status' => 'error', 'message' => 'Old password is incorrect.']);
    exit();
}

// Hash new password and update
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
if ($stmt->execute([$newHash, $_SESSION['user_id']])) {
    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update password.']);
}
exit();
