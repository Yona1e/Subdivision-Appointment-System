<?php
$conn = new mysqli("localhost", "root", "", "appointment-system");

$data = json_decode(file_get_contents("php://input"), true);

$title = $data['title'];
$start = $data['start'];
$end = $data['end'];

$stmt = $conn->prepare("INSERT INTO reservations (title, start, end) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $title, $start, $end);
$stmt->execute();

echo json_encode(["success" => true]);
