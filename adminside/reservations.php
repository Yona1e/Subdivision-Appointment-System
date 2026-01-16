<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// Handle hide reservation (update admin_visible to FALSE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);

    $stmt = $conn->prepare("UPDATE reservations SET admin_visible = FALSE WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    if ($stmt->execute()) {
        $message = "Reservation #$reservation_id has been hidden from view.";
    } else {
        $message = "Error hiding reservation: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch ONLY approved and rejected reservations that are visible to admin
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
            WHERE LOWER(r.status) IN ('approved', 'rejected')
            AND r.admin_visible = TRUE
            ORDER BY r.status DESC, r.id DESC";

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
                        <a href="create-account.php" class="menu-link">
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
                <div class="page-header">
                Approved & Rejected Reservations
                </div>
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
                        These are all reservations that have been <strong>approved</strong> or
                        <strong>rejected</strong>.
                        You may delete them to remove from this list.
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
                                    <th>Action</th>
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
                                        <?php if (strtolower($row['status']) === 'approved'): ?>
                                        <span class="badge bg-success">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this reservation?');">
                                            <input type="hidden" name="reservation_id"
                                                value="<?php echo $row['id']; ?>"> <button type="submit"
                                                name="delete_reservation" class="btn btn-danger btn-sm"
                                                title="Delete Reservation"> <span class="material-symbols-outlined"
                                                    style="font-size: 18px; vertical-align: middle;">delete</span>
                                                Delete </button> </form>

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
    <script src="../resident-side/javacript/sidebar.js"></script>




    <!-- SWAL IMPORT LINK -->
   

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

 <script src="../resident-side/javascript/sidebar.js"></script>


</html>

<?php $conn->close(); ?>