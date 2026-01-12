<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'database_connection.php';

$name  = $_POST['event_name'];
$start = $_POST['event_start_date'];
$end   = $_POST['event_end_date'];

if ($name == "" || $start == "" || $end == "") {
    echo json_encode(["status" => false, "msg" => "All fields are required"]);
    exit;
}

$query = "INSERT INTO calendar_event_master (event_name, event_start_date, event_end_date)
          VALUES ('$name', '$start', '$end')";

if (mysqli_query($con, $query)) {
    echo json_encode(["status" => true, "msg" => "Event saved successfully!"]);
} else {
    echo json_encode(["status" => false, "msg" => mysqli_error($con)]);
}
?>
