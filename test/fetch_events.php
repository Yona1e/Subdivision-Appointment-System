<?php
$conn = new mysqli("localhost", "root", "", "appointment-system");

$result = $conn->query("SELECT id, title, start, end FROM reservations");

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => $row['start'],
        'end' => $row['end']
    ];
}

echo json_encode($events);
