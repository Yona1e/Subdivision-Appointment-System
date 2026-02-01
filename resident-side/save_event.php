<?php
/**
 * save_event.php - FINAL FIXED VERSION
 * Now includes user_role support
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    echo json_encode([
        'status' => false,
        'msg' => 'Unauthorized access. Please login first.'
    ]);
    exit();
}

// Database connection for XAMPP
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'msg' => 'Database connection failed. Please try again later.'
    ]);
    exit();
}

// Get POST data and sanitize
$facility_name = isset($_POST['facility_name']) ? trim($_POST['facility_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$event_start_date = isset($_POST['event_start_date']) ? $_POST['event_start_date'] : '';
$event_end_date = isset($_POST['event_end_date']) ? $_POST['event_end_date'] : '';
$time_start = isset($_POST['time_start']) ? $_POST['time_start'] : '';
$time_end = isset($_POST['time_end']) ? $_POST['time_end'] : '';
$note = isset($_POST['note']) ? trim($_POST['note']) : '';
$cost = isset($_POST['cost']) ? floatval($_POST['cost']) : 0;

// Get user ID and role from session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Resident';

// Validate required fields
if (empty($facility_name)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Please select a facility.'
    ]);
    exit();
}

if (empty($phone)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Please enter your phone number.'
    ]);
    exit();
}

if (empty($event_start_date)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Please select a date.'
    ]);
    exit();
}

if (empty($time_start) || empty($time_end)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Please select a time slot.'
    ]);
    exit();
}

// Validate phone number format
if (!preg_match('/^\d{10,11}$/', $phone)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Invalid phone number format. Please enter 10-11 digits.'
    ]);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_start_date)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Invalid date format.'
    ]);
    exit();
}

// Check if date is in the past
$selectedDate = new DateTime($event_start_date);
$today = new DateTime('today');

if ($selectedDate < $today) {
    echo json_encode([
        'status' => false,
        'msg' => 'Cannot book a date in the past. Please select a future date.'
    ]);
    exit();
}

// Validate allowed facilities
$allowed_facilities = ['Chapel', 'Basketball Court', 'Multipurpose Hall', 'Tennis Court'];
if (!in_array($facility_name, $allowed_facilities)) {
    echo json_encode([
        'status' => false,
        'msg' => 'Invalid facility selected.'
    ]);
    exit();
}

// Calculate cost based on facility and time duration
$facilityPrices = [
    'Chapel' => 500,
    'Basketball Court' => 100,
    'Multipurpose Hall' => 600,
    'Tennis Court' => 400
];

$costPerHour = isset($facilityPrices[$facility_name]) ? $facilityPrices[$facility_name] : 350;

$startTime = new DateTime($time_start);
$endTime = new DateTime($time_end);
$interval = $startTime->diff($endTime);
$hours = $interval->h + ($interval->i / 60);

$calculatedCost = $costPerHour * $hours;

if ($cost > 0 && abs($cost - $calculatedCost) > 0.01) {
    error_log("Cost mismatch detected. Submitted: $cost, Calculated: $calculatedCost");
    $cost = $calculatedCost;
} else if ($cost == 0) {
    $cost = $calculatedCost;
}

if ($cost < 0) {
    echo json_encode([
        'status' => false,
        'msg' => 'Invalid cost calculation. Please try again.'
    ]);
    exit();
}

// Handle payment proof file upload
$payment_proof_path = null;

if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['payment_proof'];

    $maxFileSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxFileSize) {
        echo json_encode([
            'status' => false,
            'msg' => 'Payment proof file is too large. Maximum size is 5MB.'
        ]);
        exit();
    }

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode([
            'status' => false,
            'msg' => 'Invalid file type. Only JPG, PNG, and GIF images are allowed.'
        ]);
        exit();
    }

    $uploadDir = '../uploads/payment_proofs/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = 'payment_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $payment_proof_path = 'uploads/payment_proofs/' . $uniqueFileName;
    } else {
        echo json_encode([
            'status' => false,
            'msg' => 'Failed to upload payment proof. Please try again.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'status' => false,
        'msg' => 'Payment proof is required. Please upload a screenshot of your payment.'
    ]);
    exit();
}

try {
    // Check for conflicting reservations
    $checkQuery = "SELECT id, time_start, time_end, status
                   FROM reservations 
                   WHERE facility_name = :facility_name 
                   AND event_start_date = :event_start_date 
                   AND status IN ('pending', 'approved')
                   AND overwriteable = 0
                   AND (
                       (time_start < :time_end AND time_end > :time_start)
                   )";

    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([
        ':facility_name' => $facility_name,
        ':event_start_date' => $event_start_date,
        ':time_start' => $time_start,
        ':time_end' => $time_end
    ]);

    $conflict = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
        if ($payment_proof_path && file_exists('../' . $payment_proof_path)) {
            unlink('../' . $payment_proof_path);
        }

        error_log("Booking conflict detected: " . print_r($conflict, true));

        echo json_encode([
            'status' => false,
            'msg' => 'This time slot is already booked. Please select another time or date.'
        ]);
        exit();
    }

    // Check if user_role column exists
    $checkColumnQuery = "SHOW COLUMNS FROM reservations LIKE 'user_role'";
    $columnStmt = $conn->prepare($checkColumnQuery);
    $columnStmt->execute();
    $columnExists = $columnStmt->fetch();

    // Insert with or without user_role based on column existence
    if ($columnExists) {
        $sql = "INSERT INTO reservations 
                (user_id, user_role, facility_name, phone, event_start_date, event_end_date, 
                 time_start, time_end, note, cost, payment_proof, status, admin_visible, resident_visible, created_at) 
                VALUES 
                (:user_id, :user_role, :facility_name, :phone, :event_start_date, :event_end_date, 
                 :time_start, :time_end, :note, :cost, :payment_proof, 'pending', TRUE, TRUE, NOW())";

        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':user_role' => $user_role,
            ':facility_name' => $facility_name,
            ':phone' => $phone,
            ':event_start_date' => $event_start_date,
            ':event_end_date' => $event_end_date,
            ':time_start' => $time_start,
            ':time_end' => $time_end,
            ':note' => $note,
            ':cost' => $cost,
            ':payment_proof' => $payment_proof_path
        ]);
    } else {
        $sql = "INSERT INTO reservations 
                (user_id, facility_name, phone, event_start_date, event_end_date, 
                 time_start, time_end, note, cost, payment_proof, status, admin_visible, resident_visible, created_at) 
                VALUES 
                (:user_id, :facility_name, :phone, :event_start_date, :event_end_date, 
                 :time_start, :time_end, :note, :cost, :payment_proof, 'pending', TRUE, TRUE, NOW())";

        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':facility_name' => $facility_name,
            ':phone' => $phone,
            ':event_start_date' => $event_start_date,
            ':event_end_date' => $event_end_date,
            ':time_start' => $time_start,
            ':time_end' => $time_end,
            ':note' => $note,
            ':cost' => $cost,
            ':payment_proof' => $payment_proof_path
        ]);
    }

    if ($result) {
        $reservation_id = $conn->lastInsertId();

        echo json_encode([
            'status' => true,
            'msg' => 'Reservation saved successfully! Your booking is pending approval.',
            'reservation_id' => $reservation_id,
            'cost' => $cost
        ]);
    } else {
        if ($payment_proof_path && file_exists('../' . $payment_proof_path)) {
            unlink('../' . $payment_proof_path);
        }

        echo json_encode([
            'status' => false,
            'msg' => 'Failed to save reservation. Please try again.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());

    if ($payment_proof_path && file_exists('../' . $payment_proof_path)) {
        unlink('../' . $payment_proof_path);
    }

    echo json_encode([
        'status' => false,
        'msg' => 'An error occurred while saving your reservation. Please try again.'
    ]);
}

$conn = null;
?>