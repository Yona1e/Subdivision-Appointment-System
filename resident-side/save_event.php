<?php
/**
 * save_event.php
 * Handles saving facility reservations to the database
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

try {
    // Check for conflicting reservations
    $checkQuery = "SELECT id, time_start, time_end 
                   FROM reservations 
                   WHERE facility_name = :facility_name 
                   AND event_start_date = :event_start_date 
                   AND status != 'cancelled'
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
        echo json_encode([
            'status' => false,
            'msg' => 'This time slot is already booked. Please select another time or date.'
        ]);
        exit();
    }
    
    // Insert new reservation
    $sql = "INSERT INTO reservations 
            (user_id, facility_name, phone, event_start_date, event_end_date, 
             time_start, time_end, note, status, created_at) 
            VALUES 
            (:user_id, :facility_name, :phone, :event_start_date, :event_end_date, 
             :time_start, :time_end, :note, 'pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':facility_name' => $facility_name,
        ':phone' => $phone,
        ':event_start_date' => $event_start_date,
        ':event_end_date' => $event_end_date,
        ':time_start' => $time_start,
        ':time_end' => $time_end,
        ':note' => $note
    ]);
    
    if ($result) {
        $reservation_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => true,
            'msg' => 'Reservation saved successfully! Your booking is pending approval.',
            'reservation_id' => $reservation_id
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'msg' => 'Failed to save reservation. Please try again.'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => false,
        'msg' => 'An error occurred while saving your reservation. Please try again.'
    ]);
}

$conn = null;
?>