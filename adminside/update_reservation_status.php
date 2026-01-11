<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required POST data is present
if (!isset($_POST['reservation_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$reservationId = intval($_POST['reservation_id']);
$newStatus = $_POST['status'];

// Validate status
if (!in_array($newStatus, ['Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Update the reservation status
$sql = "UPDATE reservations SET Status = ?, ApprovedBy = ?, ApprovalDate = NOW() WHERE ReservationID = ? AND Status = 'Pending'";
$stmt = $conn->prepare($sql);
$adminId = $_SESSION['userID'];
$stmt->bind_param("sii", $newStatus, $adminId, $reservationId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Reservation status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or already processed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update reservation status']);
}

$stmt->close();
$conn->close();
?>