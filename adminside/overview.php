<?php
session_start();

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current user data for sidebar
$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Profile picture fallback
$profilePic = !empty($user['ProfilePictureURL'])
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

// Verify the file exists, otherwise use default
if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

// User's full name for sidebar
$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);

// FETCH DASHBOARD COUNTS
$pending_query = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE status='pending' AND admin_visible = 1");
$pending_count = $pending_query->fetch_assoc()['total'];

// Changed: Rejected requests count
$rejected_query = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE status='rejected' AND admin_visible = 1");
$rejected_count = $rejected_query->fetch_assoc()['total'];

$total_accounts_query = $conn->query("SELECT COUNT(*) AS total FROM users WHERE Status='Active'");
$total_accounts = $total_accounts_query->fetch_assoc()['total'];

// NEW: Completed reservations this week
$completed_week_query = $conn->query("
    SELECT COUNT(*) AS total 
    FROM reservations 
    WHERE status='approved' 
    AND event_end_date < CURDATE() 
    AND YEARWEEK(event_end_date, 1) = YEARWEEK(CURDATE(), 1)
    AND admin_visible = 1
");
$completed_week_count = $completed_week_query->fetch_assoc()['total'];

// FETCH STATUS DATA FOR CHART
$status_query = $conn->query("
    SELECT 
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending
    FROM reservations 
    WHERE admin_visible = 1
");
$status_data = $status_query->fetch_assoc();

// Recent activity log
$recent_audit_sql = "SELECT * FROM v_audit_logs_detailed ORDER BY Timestamp DESC LIMIT 10";
$recent_audit_result = $conn->query($recent_audit_sql);

// Pending requests (for Quick Actions in chart section)
$pending_requests_sql = "SELECT r.*, u.FirstName, u.LastName 
                         FROM reservations r
                         JOIN users u ON r.user_id = u.user_id
                         WHERE r.status='pending' AND r.admin_visible = 1
                         ORDER BY r.created_at DESC";
$pending_requests_result = $conn->query($pending_requests_sql);

// Completed reservations this week (for bottom card)
$completed_week_sql = "SELECT r.*, u.FirstName, u.LastName 
                       FROM reservations r
                       JOIN users u ON r.user_id = u.user_id
                       WHERE r.status='approved' 
                       AND r.event_end_date < CURDATE() 
                       AND YEARWEEK(r.event_end_date, 1) = YEARWEEK(CURDATE(), 1)
                       AND r.admin_visible = 1
                       ORDER BY r.event_end_date DESC";
$completed_week_result = $conn->query($completed_week_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="overview.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <div class="profile-section">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-photo">
                    <div class="profile-info">
                        <p class="profile-name"><?= $userName ?></p>
                        <p class="profile-role">Admin</p>
                    </div>
                </div>
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
                        <a href="manageaccounts.php" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">Manage Accounts</span>
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
                <a href="log-out.php" method="post" class="logout-link menu-link">
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
                    <div class="card p-3 shadow-sm" style="background:rgba(40, 167, 69, 0.8);">
                        <h6 class="text-white">Completed Reservations This Week</h6>
                        <h2 class="text-white"><?php echo $completed_week_count; ?></h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm" style="background:rgba(220, 53, 69, 0.8);">
                        <h6 class="text-white">Rejected Requests</h6>
                        <h2 class="text-white"><?php echo $rejected_count; ?></h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm" style="background: #0b5ed7;">
                        <h6 class="text-white">Active Accounts</h6>
                        <h2 class="text-white"><?php echo $total_accounts; ?></h2>
                    </div>
                </div>
            </div>

            <!-- CHART SECTION -->
    <div class="card-header">
                </div>
                <div class="card-body p-3">
                    
                    <div class="row g-4">
                        <!-- Left Column: Chart -->
                        <div class="col-lg-8">
                            <div class="card shadow-sm border h-100">
                                <div class="card-header d-flex justify-content-between align-items-center"> 
                    <h5 class="mb-0">Reservation Status Overview</h5>
                                </div>
                                <div class="card-body"><canvas id="myChart" style="max-height: 400px;"></canvas> </div>
                               
                                
                            </div>
                        </div>

                        <!-- Right Column: Quick Actions -->
                        <div class="col-lg-4">
    <div class="card shadow-sm border h-100 d-flex flex-column">
        <div class="card-header d-flex justify-content-between align-items-center"> 
                    <h5 class="mb-0">Pending Requests</h5>
                                </div>
        <div class="card-body p-3 flex-grow-1 d-flex flex-column">
            <?php if ($pending_requests_result && $pending_requests_result->num_rows > 0): ?>
                <div class="flex-grow-1" style="max-height: 320px; overflow-y: auto;">
                    <?php while($req = $pending_requests_result->fetch_assoc()): ?>
                        <div class="request-item mb-3 p-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($req['facility_name']) ?></strong>
                                    <div class="text-muted small">
                                        <?= htmlspecialchars($req['FirstName'] . ' ' . $req['LastName']) ?>
                                    </div>
                                </div>
                                <span class="badge bg-warning text-white">Pending</span>
                            </div>
                            <div class="small text-muted">
                                <?= date('M d, Y', strtotime($req['event_start_date'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <a href="reserverequests.php" class="btn btn-warning w-100 mt-3">View All</a>
            <?php else: ?>
                <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                    <p class="text-muted mb-0">No Pending Requests</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
                        </div>
                    </div>
                </div>

            <!-- RECENT ACTIVITY LOG -->
            <?php if ($recent_audit_result && $recent_audit_result->num_rows > 0): ?>
            <div class="card recent-activity-card mt-4">
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
                            <div class="fw-bold">
                                <?= htmlspecialchars($adminName); ?>
                                <?= $actionText; ?> a reservation request
                            </div>
                            <div class="text-muted small">
                                <?= htmlspecialchars($timestamp); ?>
                            </div>
                            <div class="small">
                                <span><strong>Resident:</strong>
                                    <?= htmlspecialchars($residentName); ?>
                                </span><br>
                                <span><strong>Facility:</strong>
                                    <?= htmlspecialchars($facilityName); ?>
                                </span><br>
                                <span><strong>Date:</strong>
                                    <?= htmlspecialchars($eventDate); ?>
                                </span>
                                <?php if ($timeRange): ?>
                                <br><span><strong>Time:</strong>
                                    <?= htmlspecialchars($timeRange); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">No recent activity found.</div>
                </div>
            </div>
            <?php endif; ?>

        </div> <!-- END MAIN CONTENT -->

    </div> <!-- END APP-LAYOUT -->

    <script>
        // Pass PHP data to JavaScript
        const chartData = {
            approved: <?php echo $status_data['approved'] ?? 0; ?>,
            rejected: <?php echo $status_data['rejected'] ?? 0; ?>,
            pending: <?php echo $status_data['pending'] ?? 0; ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
    <script src="../resident-side/javascript/chart.js"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>

</body>

</html>

<?php
$conn->close();
?>