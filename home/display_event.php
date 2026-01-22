<?php
session_start();
header('Content-Type: application/json');

// DB connection
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
        'status' => false,
        'msg' => 'Database connection failed'
    ]);
    exit;
}

// Fetch ALL approved reservations (ALL facilities)
$stmt = $conn->prepare("
    SELECT 
        facility_name,
        event_start_date,
        time_start,
        time_end
    FROM reservations
    WHERE status = 'approved'
");
$stmt->execute();

$events = [];
$facilities = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $events[] = [
        'title'  => $row['facility_name'], // REQUIRED by your JS
        'start'  => $row['event_start_date'] . ' ' . $row['time_start'],
        'end'    => $row['event_start_date'] . ' ' . $row['time_end'],
        'status' => 'approved'
    ];

    // Track unique facilities
    $facilities[$row['facility_name']] = true;
}

echo json_encode([
    'status' => true,
    'data' => $events,
    'facilities' => array_keys($facilities)
]);
