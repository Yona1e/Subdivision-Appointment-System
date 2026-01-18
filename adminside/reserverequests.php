<?php
session_start();

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_reservation']) || isset($_POST['reject_reservation'])) {
        $reservation_id = intval($_POST['reservation_id']);
        $new_status = isset($_POST['approve_reservation']) ? 'approved' : 'rejected';
        $admin_id = $_SESSION['user_id'];
        
        $conn->query("SET @current_admin_id = $admin_id");
        
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $reservation_id);
        if ($stmt->execute()) {
            $message = "Reservation #$reservation_id has been marked as " . ucfirst($new_status) . ".";
        } else {
            $message = "Error updating reservation: " . $stmt->error;
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    
    if (!isset($_SESSION['hidden_reservations'])) {
        $_SESSION['hidden_reservations'] = [];
    }
    if (!in_array($reservation_id, $_SESSION['hidden_reservations'])) {
        $_SESSION['hidden_reservations'][] = $reservation_id;
    }
    $message = "Reservation removed from view.";
}

$hidden_ids = isset($_SESSION['hidden_reservations']) ? $_SESSION['hidden_reservations'] : [];
$hidden_clause = "";
if (!empty($hidden_ids)) {
    $hidden_ids_str = implode(',', array_map('intval', $hidden_ids));
    $hidden_clause = " AND r.id NOT IN ($hidden_ids_str)";
}

$notes_column_exists = false;
$result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'notes'");
if ($result && $result->num_rows > 0) {
    $notes_column_exists = true;
}

$res_sql = "SELECT
                r.id,
                r.facility_name,
                r.phone,
                r.event_start_date,
                r.event_end_date,
                r.time_start,
                r.time_end,
                r.status,
                " . ($notes_column_exists ? "r.notes," : "") . "
                u.FirstName,
                u.LastName
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE LOWER(r.status) = 'pending'
            $hidden_clause
            ORDER BY r.id DESC";

$reservations = $conn->query($res_sql);

if (!$reservations) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="adminside.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
</head>
<body>
<div class="app-layout">

    <aside class="sidebar">
        <header class="sidebar-header">
            <img src="../asset/logo.png" alt="Header Logo" class="header-logo">
            <button class="sidebar-toggle">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
        </header>

        <div class="sidebar-content">
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
                        <img src="../asset/profile.png" alt="Create Account Icon" class="menu-icon">
                        <span class="menu-label">Create Account</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="logout-section">
            <a  href="../adminside/log-out.php" method="post" class="logout-link">
                <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                <span class="menu-label">Log Out</span>
            </a>
        </div>
    </aside>

    <div class="main-content">
        <div class="reservation-card" >
            <div class="page-header">
                Pending Reservations
            </div>

            <div class="card-body">

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="table-responsive mt-3">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Facility</th>
                                <th>Phone</th>
                                <th>Event Date</th>
                                <th>Time</th>
                                <th>User</th>
                                <?php if ($notes_column_exists): ?>
                                <th>Notes</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($reservations->num_rows > 0): ?>
                                <?php while ($row = $reservations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
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
                                    <td><?php echo $row['FirstName'] . " " . $row['LastName']; ?></td>
                                    <?php if ($notes_column_exists): ?>
                                    <td>
                                        <?php echo !empty($row['notes']) ? htmlspecialchars($row['notes']) : "<span class='text-muted'>No notes</span>"; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td><span class="badge bg-warning text-dark"><?php echo ucfirst($row['status']); ?></span></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="approve_reservation" class="btn btn-success btn-sm mb-1">
                                                Approve
                                            </button>
                                            <button type="submit" name="reject_reservation" class="btn btn-danger btn-sm mb-1">
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $notes_column_exists ? 9 : 8; ?>" class="text-center py-5">
                                        <div class="alert alert-info mb-0">
                                            <h5 class="alert-heading mb-3">No pending reservations!</h5>
                                            <p>All reservations have been approved or rejected.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
<script src="../resident-side/javascript/sidebar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>