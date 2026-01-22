 <?php
session_start();

// Check if user is logged in as Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['status' => false, 'msg' => 'Unauthorized access']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['status' => false, 'msg' => 'Database connection failed']);
    exit();
}

// Get POST data
$facility_name = isset($_POST['facility_name']) ? trim($_POST['facility_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$event_start_date = isset($_POST['event_start_date']) ? trim($_POST['event_start_date']) : '';
$event_end_date = isset($_POST['event_end_date']) ? trim($_POST['event_end_date']) : '';
$time_start = isset($_POST['time_start']) ? trim($_POST['time_start']) : '';
$time_end = isset($_POST['time_end']) ? trim($_POST['time_end']) : '';
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Validate required fields
if (empty($facility_name)) {
    echo json_encode(['status' => false, 'msg' => 'Facility name is required']);
    exit();
}

if (empty($phone)) {
    echo json_encode(['status' => false, 'msg' => 'Phone number is required']);
    exit();
}

// Validate Philippine phone number
if (strlen($phone) !== 11 || !preg_match('/^09\d{9}$/', $phone)) {
    echo json_encode(['status' => false, 'msg' => 'Invalid Philippine phone number format']);
    exit();
}

if (empty($event_start_date) || empty($event_end_date)) {
    echo json_encode(['status' => false, 'msg' => 'Event dates are required']);
    exit();
}

if (empty($time_start) || empty($time_end)) {
    echo json_encode(['status' => false, 'msg' => 'Time slots are required']);
    exit();
}

// Check if slot is already booked
try {
    $checkSql = "SELECT COUNT(*) as count FROM reservations 
                 WHERE facility_name = :facility_name 
                 AND event_start_date = :event_start_date
                 AND status IN ('confirmed', 'approved', 'pending')
                 AND (
                     (time_start < :time_end AND time_end > :time_start)
                 )";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([
        ':facility_name' => $facility_name,
        ':event_start_date' => $event_start_date,
        ':time_start' => $time_start,
        ':time_end' => $time_end
    ]);
    
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo json_encode(['status' => false, 'msg' => 'This time slot is already booked']);
        exit();
    }
    
} catch(PDOException $e) {
    echo json_encode(['status' => false, 'msg' => 'Error checking availability: ' . $e->getMessage()]);
    exit();
}

// Insert reservation with APPROVED status (auto-approval for admin quick reservations)
try {
    $user_id = $_SESSION['user_id'];
    
    $sql = "INSERT INTO reservations (
                user_id,
                facility_name,
                phone,
                event_start_date,
                event_end_date,
                time_start,
                time_end,
                note,
                status,
                created_at
            ) VALUES (
                :user_id,
                :facility_name,
                :phone,
                :event_start_date,
                :event_end_date,
                :time_start,
                :time_end,
                :note,
                'approved',
                NOW()
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':facility_name' => $facility_name,
        ':phone' => $phone,
        ':event_start_date' => $event_start_date,
        ':event_end_date' => $event_end_date,
        ':time_start' => $time_start,
        ':time_end' => $time_end,
        ':note' => $note
    ]);
    
    $reservation_id = $conn->lastInsertId();
    
    echo json_encode([
        'status' => true,
        'msg' => 'Reservation created and approved successfully',
        'reservation_id' => $reservation_id
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['status' => false, 'msg' => 'Error saving reservation: ' . $e->getMessage()]);
}
?> 