<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'msg' => 'Database error', 'data' => []]);
    exit();
}

$facility = isset($_GET['facility']) ? trim($_GET['facility']) : null;

try {
    if ($facility) {
        // Fetch reservations for specific facility
        $sql = "SELECT 
                    r.reservation_id,
                    r.facility_name,
                    r.event_start_date,
                    r.event_end_date,
                    r.time_start,
                    r.time_end,
                    r.status,
                    CONCAT(u.FirstName, ' ', u.LastName) as resident_name
                FROM reservations r
                INNER JOIN users u ON r.user_id = u.user_id
                WHERE r.facility_name = :facility
                AND r.status IN ('confirmed', 'approved', 'pending')
                AND r.overwriteable = 0
                ORDER BY r.event_start_date ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':facility', $facility, PDO::PARAM_STR);
    } else {
        // Fetch all reservations
        $sql = "SELECT 
                    r.reservation_id,
                    r.facility_name,
                    r.event_start_date,
                    r.event_end_date,
                    r.time_start,
                    r.time_end,
                    r.status,
                    CONCAT(u.FirstName, ' ', u.LastName) as resident_name
                FROM reservations r
                INNER JOIN users u ON r.user_id = u.user_id
                WHERE r.status IN ('confirmed', 'approved', 'pending')
                AND r.overwriteable = 0
                ORDER BY r.event_start_date ASC";

        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format events for FullCalendar
    $events = [];
    foreach ($reservations as $reservation) {
        $events[] = [
            'id' => $reservation['reservation_id'],
            'title' => $reservation['facility_name'],
            'start' => $reservation['event_start_date'] . 'T' . $reservation['time_start'],
            'end' => $reservation['event_end_date'] . 'T' . $reservation['time_end'],
            'status' => $reservation['status'],
            'resident' => $reservation['resident_name'],
            'backgroundColor' => getColorByStatus($reservation['status']),
            'borderColor' => getColorByStatus($reservation['status'])
        ];
    }

    echo json_encode([
        'status' => true,
        'data' => $events,
        'count' => count($events)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'msg' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}

function getColorByStatus($status)
{
    switch (strtolower($status)) {
        case 'confirmed':
        case 'approved':
            return '#6366f1'; // Primary color
        case 'pending':
            return '#f59e0b'; // Warning color
        default:
            return '#64748b'; // Gray
    }
}
?>