<?php
/**
 * display_event.php
 * Retrieves all reservations from database and formats them for FullCalendar
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    echo json_encode([
        'status' => false,
        'msg' => 'Unauthorized access',
        'data' => []
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
        'msg' => 'Database connection failed',
        'data' => []
    ]);
    exit();
}

try {
    // Fetch all active reservations
    $sql = "SELECT 
                id as event_id,
                facility_name as title,
                event_start_date,
                event_end_date,
                time_start,
                time_end,
                status,
                phone,
                note,
                user_id
            FROM reservations 
            WHERE status != 'cancelled'
            ORDER BY event_start_date ASC, time_start ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for FullCalendar
    $events = [];
    
    foreach ($reservations as $reservation) {
        // Combine date and time for start and end
        $start = $reservation['event_start_date'] . ' ' . $reservation['time_start'];
        $end = $reservation['event_start_date'] . ' ' . $reservation['time_end'];
        
        // Determine color based on status
        $color = '#007bff'; // Default blue
        switch($reservation['status']) {
            case 'pending':
                $color = '#ffc107'; // Yellow/Orange
                break;
            case 'approved':
                $color = '#28a745'; // Green
                break;
            case 'rejected':
                $color = '#dc3545'; // Red
                break;
            case 'completed':
                $color = '#6c757d'; // Gray
                break;
        }
        
        // Build event object
        $event = [
            'id' => $reservation['event_id'],
            'event_id' => $reservation['event_id'],
            'title' => $reservation['title'],
            'start' => $start,
            'end' => $end,
            'color' => $color,
            'status' => $reservation['status'],
            'phone' => $reservation['phone'],
            'note' => $reservation['note'],
            'allDay' => false
        ];
        
        $events[] = $event;
    }
    
    echo json_encode([
        'status' => true,
        'msg' => 'Events loaded successfully',
        'data' => $events
    ]);
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => false,
        'msg' => 'Error fetching events',
        'data' => []
    ]);
}

$conn = null;
?>