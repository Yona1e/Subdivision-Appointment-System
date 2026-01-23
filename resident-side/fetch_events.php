<?php
$conn = new mysqli("localhost", "root", "", "appointment-system");

if ($conn->connect_error) {
    die(json_encode([]));
}

$sql = "
    SELECT 
        r.id,
        r.facility_name AS title,
        CONCAT(r.event_start_date, ' ', r.time_start) AS start,
        CONCAT(r.event_start_date, ' ', r.time_end) AS end,
        r.status,
        u.role AS user_role
    FROM reservations r
    JOIN users u ON r.user_id = u.id
";

$result = $conn->query($sql);

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => $row['start'],
        'end' => $row['end'],
        'status' => $row['status'],
        'user_role' => $row['user_role']
    ];
}

echo json_encode($events);
