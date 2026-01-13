<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// Handle status update to COMPLETED
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);

    $update_sql = "UPDATE reservations SET status = 'Completed' WHERE id = $reservation_id";
    if ($conn->query($update_sql)) {
            }
}

// Fetch ALL approved reservations
$res_sql = "SELECT
                r.id,
                r.facility_name,
                r.phone,
                r.event_start_date,
                r.event_end_date,
                r.time_start,
                r.time_end,
                r.status,
                u.FirstName,
                u.LastName
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE LOWER(r.status) = 'approved'
            ORDER BY r.id DESC";

$reservations = $conn->query($res_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="admin.css">
</head>

<body>

    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <img src="../asset/logo.png" alt="Header Logo" class="header-logo">
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <!-- Menu List -->
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="overview.php" class="menu-link">
                            <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reserverequests.php" class="menu-link">
                            <img src="../asset/makeareservation.png" alt="Make a Reservation Icon" class="menu-icon">
                            <span class="menu-label">Requests</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reservations.php" class="menu-link">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">My Account</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">Create Account</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="reservation-card">
            <h1 class="mb-4">📋 Approved Reservations</h1>
            <div class="container mt-5">
                

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <h5>
                        <?php echo $message; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    These are all reservations that have been <strong>approved</strong>.
                    You may mark them as <strong>Completed</strong> once the event has finished.
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Facility</th>
                                <th>Phone</th>
                                <th>Event Date</th>
                                <th>Time</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $reservations->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $row['id']; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['facility_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['phone']); ?>
                                </td>

                                <td>
                                    <?php
                                echo date('M d, Y', strtotime($row['event_start_date']));
                                if ($row['event_start_date'] != $row['event_end_date']) {
                                    echo " - " . date('M d, Y', strtotime($row['event_end_date']));
                                }
                                ?>
                                </td>

                                <td>
                                    <?php
                                echo date('g:i A', strtotime($row['time_start'])) . " - " .
                                     date('g:i A', strtotime($row['time_end']));
                                ?>
                                </td>

                                <td>
                                    <?php echo $row['FirstName'] . " " . $row['LastName']; ?>
                                </td>

                                <td>
                                    <span class="badge bg-success">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="complete_reservation"
                                            class="btn btn-primary btn-sm">
                                            Mark as Completed
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div> <!-- END sidebar-content -->
         </div>                       
    </div> <!-- END app-layout -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

<?php $conn->close(); ?>