<?php
/**
 * save_event.php
 * Handles saving facility reservations to the database with payment proof upload
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
} catch(PDOException $e) {
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

// Get user ID from session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

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

// Handle payment proof file upload
$payment_proof_path = null;

if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['payment_proof'];
    
    // Validate file size (max 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxFileSize) {
        echo json_encode([
            'status' => false,
            'msg' => 'Payment proof file is too large. Maximum size is 5MB.'
        ]);
        exit();
    }
    
    // Validate file type (only images)
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
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/payment_proofs/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = 'payment_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueFileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Store relative path for database
        $payment_proof_path = 'uploads/payment_proofs/' . $uniqueFileName;
    } else {
        echo json_encode([
            'status' => false,
            'msg' => 'Failed to upload payment proof. Please try again.'
        ]);
        exit();
    }
} else {
    // Payment proof is required
    echo json_encode([
        'status' => false,
        'msg' => 'Payment proof is required. Please upload a screenshot of your payment.'
    ]);
    exit();
}

try {
    // Check for conflicting reservations (only check visible reservations)
    $checkQuery = "SELECT id, time_start, time_end 
                   FROM reservations 
                   WHERE facility_name = :facility_name 
                   AND event_start_date = :event_start_date 
                   AND status != 'cancelled'
                   AND admin_visible = TRUE
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
        // Delete uploaded file if there's a conflict
        if ($payment_proof_path && file_exists('../' . $payment_proof_path)) {
            unlink('../' . $payment_proof_path);
        }
        
        echo json_encode([
            'status' => false,
            'msg' => 'This time slot is already booked. Please select another time or date.'
        ]);
        exit();
    }
    
    // Insert new reservation with payment proof and visibility defaults
    $sql = "INSERT INTO reservations 
            (user_id, facility_name, phone, event_start_date, event_end_date, 
             time_start, time_end, note, payment_proof, status, admin_visible, resident_visible, created_at) 
            VALUES 
            (:user_id, :facility_name, :phone, :event_start_date, :event_end_date, 
             :time_start, :time_end, :note, :payment_proof, 'pending', TRUE, TRUE, NOW())";
    
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
        ':payment_proof' => $payment_proof_path
    ]);
    
    if ($result) {
        $reservation_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => true,
            'msg' => 'Reservation saved successfully! Your booking is pending approval.',
            'reservation_id' => $reservation_id
        ]);
    } else {
        // Delete uploaded file if database insert fails
        if ($payment_proof_path && file_exists('../' . $payment_proof_path)) {
            unlink('../' . $payment_proof_path);
        }
        
        echo json_encode([
            'status' => false,
            'msg' => 'Failed to save reservation. Please try again.'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    
    // Delete uploaded file if there's a database error
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