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
$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL']))
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

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

// Recent activity log (UPDATED to use JOINs instead of View)
$recent_audit_sql = "
    SELECT 
        a.LogID, 
        a.ActionType, 
        a.Timestamp, 
        a.EntityDetails,
        u1.FirstName as AdminFirst, u1.LastName as AdminLast, 
        u2.FirstName as ResidentFirst, u2.LastName as ResidentLast
    FROM auditlogs a
    LEFT JOIN users u1 ON a.AdminID = u1.user_id
    LEFT JOIN users u2 ON a.UserID = u2.user_id
    ORDER BY a.Timestamp DESC 
    LIMIT 10
";
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
                        <a href="quick-reservation/quick-reservation.php" class="menu-link">
                            <img src="../asset/Vector.png" class="menu-icon">
                            <span class="menu-label">Quick Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="manageaccounts.php" class="menu-link">
                            <img src="../asset/manage2.png" alt="Manage Accounts Icon" class="menu-icon">
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
                        <h2 class="text-white"><?php echo $completed_week_count; ?>
                        </h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm" style="background:rgba(220, 53, 69, 0.8);">
                        <h6 class="text-white">Rejected Requests</h6>
                        <h2 class="text-white">
                            <?php echo $rejected_count; ?>
                        </h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm" style="background: #0b5ed7;">
                        <h6 class="text-white">Active Accounts</h6>
                        <h2 class="text-white">
                            <?php echo $total_accounts; ?>
                        </h2>
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
                                        <?php while ($req = $pending_requests_result->fetch_assoc()): ?>
                                            <div class="request-item mb-3 p-2 border-bottom">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <strong>
                                                            <?= htmlspecialchars($req['facility_name']) ?>
                                                        </strong>
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
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal"
                            data-bs-target="#auditModal">
                            View All
                        </button>
                    </div>
                    <div class="card-body p-3" style="max-height: 500px; overflow-y: auto;">
                        <?php while ($log = $recent_audit_result->fetch_assoc()): ?>
                            <?php
                            $actionMessage = '';
                            $details = json_decode($log['EntityDetails'], true) ?? [];

                            switch ($log['ActionType']) {
                                case 'Approved':
                                    $bgClass = 'bg-success';
                                    $iconSymbol = 'check_circle';
                                    $actionMessage = 'approved a reservation request';
                                    break;
                                case 'Rejected':
                                    $bgClass = 'bg-danger';
                                    $iconSymbol = 'cancel';
                                    $actionMessage = 'rejected a reservation request';
                                    break;
                                case 'Event_Created': // Admin created reservation
                                    $bgClass = 'bg-success';
                                    $iconSymbol = 'check_circle';
                                    $actionMessage = 'occupied a reservation slot';
                                    break;
                                case 'Updated':
                                    $bgClass = 'bg-warning';
                                    $iconSymbol = 'edit';
                                    $actionMessage = 'updated a reservation request';
                                    break;
                                default: // Legacy or trigger created
                                    $bgClass = 'bg-secondary';
                                    $iconSymbol = 'info';
                                    $actionMessage = 'performed an action';
                            }

                            // Admin Name
                            $adminName = trim(($log['AdminFirst'] ?? '') . ' ' . ($log['AdminLast'] ?? '')) ?: 'System';
                            $timestamp = date('F d, Y \a\t g:i A', strtotime($log['Timestamp']));

                            // JSON Parsing for Details
                            $details = json_decode($log['EntityDetails'], true) ?? [];
                            $residentName = trim(($log['ResidentFirst'] ?? '') . ' ' . ($log['ResidentLast'] ?? '')) ?: 'Unknown';
                            $facilityName = $details['facility_name'] ?? 'Unknown Facility';
                            $eventDate = isset($details['event_start_date']) ? date('F d, Y', strtotime($details['event_start_date'])) : 'N/A';

                            $timeRange = '';
                            if (!empty($details['time_start']) && !empty($details['time_end'])) {
                                $timeRange = date('g:i A', strtotime($details['time_start'])) . ' - ' . date('g:i A', strtotime($details['time_end']));
                            }
                            ?>
                            <div
                                class="d-flex align-items-start audit-entry <?= $bgClass ?> bg-opacity-10 border border-<?= str_replace('bg-', '', $bgClass); ?>">
                                <div class="me-2 mt-1">
                                    <span
                                        class="material-symbols-outlined text-white bg-<?= str_replace('bg-', '', $bgClass); ?> rounded-circle d-flex align-items-center justify-content-center">
                                        <?= $iconSymbol ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($adminName); ?>
                                        <?= $actionMessage; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?= htmlspecialchars($timestamp); ?>
                                    </div>
                                    <div class="small">
                                        <?php if ($log['ActionType'] !== 'Event_Created'): ?>
                                            <span><strong>Resident:</strong>
                                                <?= htmlspecialchars($residentName); ?>
                                            </span><br>
                                        <?php endif; ?>
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal"
                            data-bs-target="#auditModal">
                            View All
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">No recent activity found.</div>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- END MAIN CONTENT -->

    </div> <!-- END APP-LAYOUT -->

    <!-- AUDIT LOG MODAL -->
    <div class="modal fade" id="auditModal" tabindex="-1" aria-labelledby="auditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="auditModalLabel">All Activity Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Search & Filter -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <input type="text" id="auditSearch" class="form-control" placeholder="Search logs...">
                        </div>
                        <div class="col-md-6 d-flex align-items-center flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input audit-filter" type="checkbox" value="Approved"
                                    id="filterApproved">
                                <label class="form-check-label" for="filterApproved">Approved</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input audit-filter" type="checkbox" value="Rejected"
                                    id="filterRejected">
                                <label class="form-check-label" for="filterRejected">Rejected</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input audit-filter" type="checkbox" value="Event_Created"
                                    id="filterAdmin">
                                <label class="form-check-label" for="filterAdmin">Admin Occupied</label>
                            </div>
                        </div>
                    </div>

                    <!-- Log Container -->
                    <div id="auditLogContainer" class="d-flex flex-column gap-2">
                        <!-- Ajax Content Here -->
                        <div class="text-center py-4 text-muted">Loading logs...</div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div class="text-muted small" id="auditPaginationInfo">Showing 0-0 of 0</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="auditPagination">
                            <!-- JS Pagination -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let currentPage = 1;

            const modal = document.getElementById('auditModal');
            const container = document.getElementById('auditLogContainer');
            const searchInput = document.getElementById('auditSearch');
            const filters = document.querySelectorAll('.audit-filter');
            const pagination = document.getElementById('auditPagination');
            const pageInfo = document.getElementById('auditPaginationInfo');

            let debounceTimer;

            // Fetch Logs Function
            function fetchLogs(page = 1) {
                const search = searchInput.value;
                const checkedFilters = Array.from(filters)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                // Build Query Params
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('search', search);
                checkedFilters.forEach((f, i) => params.append(`filters[${i}]`, f));

                // Show Loading
                container.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>';

                fetch('fetch_audit_logs.php?' + params.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            renderLogs(data.data);
                            renderPagination(data.pagination);
                        } else {
                            container.innerHTML = '<div class="alert alert-danger">Error loading logs</div>';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        container.innerHTML = '<div class="alert alert-danger">Connection error</div>';
                    });
            }

            // Render Logs HTML
            function renderLogs(logs) {
                if (logs.length === 0) {
                    container.innerHTML = '<div class="alert alert-info text-center">No logs found matching criteria.</div>';
                    return;
                }

                let html = '';
                logs.forEach(log => {
                    const bgClass = log.bg_class;
                    const borderClass = bgClass.replace('bg-', 'border-');
                    const textClass = bgClass.replace('bg-', '');

                    html += `
                <div class="d-flex align-items-start audit-entry ${bgClass} bg-opacity-10 border ${borderClass}">
                    <div class="me-2 mt-1">
                        <span class="material-symbols-outlined text-white ${textClass === 'secondary' ? 'bg-secondary' : 'bg-' + textClass} rounded-circle d-flex align-items-center justify-content-center" style="width:30px; height:30px; font-size:18px;">
                            ${log.icon}
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">
                            ${log.admin} ${log.action_message}
                        </div>
                        <div class="text-muted small mb-1">
                            ${log.timestamp}
                        </div>
                        <div class="small text-secondary">
                            ${log.resident ? `<span><strong>Resident:</strong> ${log.resident}</span><br>` : ''}
                            <span><strong>Facility:</strong> ${log.facility}</span><br>
                            <span><strong>Date:</strong> ${log.date}</span>
                            ${log.time ? `<br><span><strong>Time:</strong> ${log.time}</span>` : ''}
                        </div>
                    </div>
                </div>`;
                });
                container.innerHTML = html;
            }

            // Render Pagination Logic
            function renderPagination(data) {
                currentPage = data.current_page;
                const total = data.total_pages;

                pageInfo.textContent = `Showing page ${currentPage} of ${total} (${data.total_records} total)`;

                let html = '';

                // Previous
                html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Prev</a>
                     </li>`;

                // Page Numbers (Simple range for now)
                for (let i = 1; i <= total; i++) {
                    if (i == 1 || i == total || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                             </li>`;
                    } else if (i == currentPage - 2 || i == currentPage + 2) {
                        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }
                }

                // Next
                html += `<li class="page-item ${currentPage >= total ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
                     </li>`;

                pagination.innerHTML = html;
            }

            // Global function for onclick pagination
            window.changePage = function (page) {
                if (page < 1) return;
                fetchLogs(page);
            };

            // Event Listeners
            modal.addEventListener('shown.bs.modal', () => {
                fetchLogs(1); // Load first page when opened
            });

            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => fetchLogs(1), 500);
            });

            filters.forEach(f => {
                f.addEventListener('change', () => fetchLogs(1));
            });
        });
    </script>

</body>

</html>

<?php
$conn->close();
?>