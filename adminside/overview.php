<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FETCH DASHBOARD COUNTS
$pending_query = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE status='pending' AND admin_visible = 1");
$pending_count = $pending_query->fetch_assoc()['total'];

$upcoming_query = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE status='approved' AND event_start_date >= CURDATE()");
$upcoming_count = $upcoming_query->fetch_assoc()['total'];

$total_accounts_query = $conn->query("SELECT COUNT(*) AS total FROM users WHERE Status='Active'");
$total_accounts = $total_accounts_query->fetch_assoc()['total'];

// Recent activity log
$recent_audit_sql = "SELECT * FROM v_audit_logs_detailed ORDER BY Timestamp DESC LIMIT 10";
$recent_audit_result = $conn->query($recent_audit_sql);

// Pending requests
$pending_requests_sql = "SELECT r.*, u.FirstName, u.LastName 
                         FROM reservations r
                         JOIN users u ON r.user_id = u.user_id
                         WHERE r.status='pending' AND r.admin_visible = 1
                         ORDER BY r.created_at DESC
                         LIMIT 10";
$pending_requests_result = $conn->query($pending_requests_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<link rel="stylesheet" href="adminside.css">
 <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">

<style>
/* Fixed height and scrollable cards */
.recent-activity-card, .pending-requests-card {
    height: 550px; /* slightly taller to occupy white space */
    overflow-y: auto;
}

/* Shorter entries */
.audit-entry {
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 8px;
    font-size: 0.9rem;
}

.audit-entry .material-symbols-outlined {
    width: 35px;
    height: 35px;
    font-size: 18px;
}

.audit-entry .flex-grow-1 div {
    margin-bottom: 2px;
}
</style>
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
            <ul class="menu-list">
                <li class="menu-item">
                    <a href="overview.php" class="menu-link active">
                        <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                        <span class="menu-label">Overview</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reserverequests.php" class="menu-link">
                        <img src="../asset/makeareservation.png" alt="Requests Icon" class="menu-icon">
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
                        <img src="../asset/profile.png" alt="Create Acc Icon" class="menu-icon">
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

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- DASHBOARD TITLE + LOGOUT + CARDS -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h1 class="mb-0">Admin Dashboard</h1>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3 shadow-sm">
                    <h6 class="text-muted">Pending Requests</h6>
                    <h2><?php echo $pending_count; ?></h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3 shadow-sm">
                    <h6 class="text-muted">Upcoming Events</h6>
                    <h2><?php echo $upcoming_count; ?></h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3 shadow-sm">
                    <h6 class="text-muted">Total Accounts</h6>
                    <h2><?php echo $total_accounts; ?></h2>
                </div>
            </div>
        </div>

        <!-- PENDING REQUESTS CARD -->
        <div class="reservation-card mt-4">
            <?php if ($pending_requests_result && $pending_requests_result->num_rows > 0): ?>
                <div class="card pending-requests-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Pending Requests</h5>
                        <a href="reserverequests.php" class="btn btn-sm">View All</a>
                    </div>
                    <div class="card-body p-3">
                        <?php while($request = $pending_requests_result->fetch_assoc()): ?>
                            <?php
                                $residentName = htmlspecialchars($request['FirstName'] . ' ' . $request['LastName']);
                                $facilityName = htmlspecialchars($request['facility_name']);
                                $eventDate = date('F d, Y', strtotime($request['event_start_date']));
                                $timeRange = date('g:i A', strtotime($request['time_start'])) . ' - ' . date('g:i A', strtotime($request['time_end']));
                                $timestamp = date('F d, Y \a\t g:i A', strtotime($request['created_at']));
                            ?>
                            <div class="d-flex align-items-start audit-entry bg-warning bg-opacity-10 border border-warning">
                                <div class="me-2 mt-1">
                                    <span class="material-symbols-outlined text-white bg-warning rounded-circle d-flex align-items-center justify-content-center">
                                        schedule
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= $residentName; ?> requested a reservation</div>
                                    <div class="text-muted small"><?= $timestamp; ?></div>
                                    <div class="small">
                                        <span><strong>Facility:</strong> <?= $facilityName; ?></span><br>
                                        <span><strong>Date:</strong> <?= $eventDate; ?></span><br>
                                        <span><strong>Time:</strong> <?= $timeRange; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Pending Requests</h5></div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">No pending requests found.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT ACTIVITY CARD -->
        <div class="reservation-card mt-4">
            <?php if ($recent_audit_result && $recent_audit_result->num_rows > 0): ?>
                <div class="card recent-activity-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="#" class="btn btn-sm">View All</a>
                    </div>
                    <div class="card-body p-3">
                        <?php while($log = $recent_audit_result->fetch_assoc()): ?>
                            <?php
                                $bgClass = 'bg-success';
                                $iconSymbol = 'check_circle';
                                $actionText = '';

                                switch($log['ActionType']) {
                                    case 'Approved': $bgClass='bg-success'; $iconSymbol='check_circle'; $actionText='approved'; break;
                                    case 'Rejected': $bgClass='bg-danger'; $iconSymbol='cancel'; $actionText='rejected'; break;
                                    case 'Event_Created': $bgClass='bg-primary'; $iconSymbol='add_circle'; $actionText='created'; break;
                                    case 'Updated': $bgClass='bg-warning'; $iconSymbol='edit'; $actionText='updated'; break;
                                }

                                $adminName = $log['AdminName'] ?? 'System';
                                $residentName = $log['ResidentName'] ?? 'Unknown';
                                $facilityName = $log['FacilityName'] ?? 'Unknown Facility';
                                $eventDate = $log['EventStartDate'] ? date('F d, Y', strtotime($log['EventStartDate'])) : 'N/A';
                                $timeRange = ($log['TimeStart'] && $log['TimeEnd']) ? date('g:i A', strtotime($log['TimeStart'])) . ' - ' . date('g:i A', strtotime($log['TimeEnd'])) : '';
                                $timestamp = date('F d, Y \a\t g:i A', strtotime($log['Timestamp']));
                            ?>
                            <div class="d-flex align-items-start audit-entry <?= $bgClass ?> bg-opacity-10 border border-<?= str_replace('bg-', '', $bgClass); ?>">
                                <div class="me-2 mt-1">
                                    <span class="material-symbols-outlined text-white bg-<?= str_replace('bg-', '', $bgClass); ?> rounded-circle d-flex align-items-center justify-content-center">
                                        <?= $iconSymbol ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= htmlspecialchars($adminName); ?> <?= $actionText; ?> a reservation request</div>
                                    <div class="text-muted small"><?= htmlspecialchars($timestamp); ?></div>
                                    <div class="small">
                                        <span><strong>Resident:</strong> <?= htmlspecialchars($residentName); ?></span><br>
                                        <span><strong>Facility:</strong> <?= htmlspecialchars($facilityName); ?></span><br>
                                        <span><strong>Date:</strong> <?= htmlspecialchars($eventDate); ?></span>
                                        <?php if ($timeRange): ?>
                                            <br><span><strong>Time:</strong> <?= htmlspecialchars($timeRange); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Recent Activity</h5></div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">No recent activity found.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div> <!-- END MAIN CONTENT -->

</div> <!-- END APP-LAYOUT -->

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
<script src="../resident-side/javascript/sidebar.js"></script>

</body>
</html>

<?php
$conn->close();
?>
