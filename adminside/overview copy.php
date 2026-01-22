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

$upcoming_query = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE status='approved' AND event_start_date >= CURDATE()");
$upcoming_count = $upcoming_query->fetch_assoc()['total'];

$total_accounts_query = $conn->query("SELECT COUNT(*) AS total FROM users WHERE Status='Active'");
$total_accounts = $total_accounts_query->fetch_assoc()['total'];

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

// Pending requests
$pending_requests_sql = "SELECT r.*, u.FirstName, u.LastName 
                         FROM reservations r
                         JOIN users u ON r.user_id = u.user_id
                         WHERE r.status='pending' AND r.admin_visible = 1
                         ORDER BY r.created_at DESC
                         LIMIT 5";
$pending_requests_result = $conn->query($pending_requests_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        /* Glassmorphism Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        /* Stat Cards */
        .stat-card {
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Gradient backgrounds for stat cards */
        .stat-pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-confirmed {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-accounts {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        /* Header */
        .dashboard-header {
            color: white;
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .dashboard-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Chart Section */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        /* Activity Feed */
        .activity-feed {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .activity-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .activity-item {
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-radius: 15px;
            border-left: 4px solid;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .activity-item.approved {
            border-left-color: #28a745;
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.05) 0%, #f8f9fa 100%);
        }

        .activity-item.rejected {
            border-left-color: #dc3545;
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05) 0%, #f8f9fa 100%);
        }

        .activity-item.created {
            border-left-color: #007bff;
            background: linear-gradient(90deg, rgba(0, 123, 255, 0.05) 0%, #f8f9fa 100%);
        }

        .activity-item.updated {
            border-left-color: #ffc107;
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.05) 0%, #f8f9fa 100%);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .activity-icon.approved { background: #28a745; }
        .activity-icon.rejected { background: #dc3545; }
        .activity-icon.created { background: #007bff; }
        .activity-icon.updated { background: #ffc107; }

        .activity-content {
            flex: 1;
        }

        .activity-main {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .activity-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
        }

        .activity-time {
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.5rem;
        }

        /* Pending Requests */
        .pending-requests {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .request-item {
            padding: 1rem;
            border-radius: 12px;
            background: #f8f9fa;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .request-item:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .view-all-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .view-all-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .dashboard-header h1 {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
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
                <a href="log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">

            <!-- Header -->
            <div class="dashboard-header animate-in">
                <h1>Welcome back, <?= explode(' ', $userName)[0] ?>! 👋</h1>
                <p>Here's what's happening with your facility reservations today.</p>
            </div>

            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="glass-card stat-card stat-pending animate-in" style="animation-delay: 0.1s">
                        <div class="stat-icon">
                            <span class="material-symbols-outlined">pending_actions</span>
                        </div>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="glass-card stat-card stat-confirmed animate-in" style="animation-delay: 0.2s">
                        <div class="stat-icon">
                            <span class="material-symbols-outlined">event_available</span>
                        </div>
                        <div class="stat-number"><?php echo $upcoming_count; ?></div>
                        <div class="stat-label">Confirmed Events</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="glass-card stat-card stat-accounts animate-in" style="animation-delay: 0.3s">
                        <div class="stat-icon">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                        <div class="stat-number"><?php echo $total_accounts; ?></div>
                        <div class="stat-label">Active Accounts</div>
                    </div>
                </div>
            </div>

            <!-- Chart and Quick Actions Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="chart-container animate-in" style="animation-delay: 0.4s">
                        <div class="chart-header">
                            <h3 class="chart-title">Reservation Overview</h3>
                        </div>
                        <canvas id="myChart" style="max-height: 350px;"></canvas>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="pending-requests animate-in" style="animation-delay: 0.5s">
                        <div class="activity-header">
                            <h3 class="activity-title">Quick Actions</h3>
                        </div>
                        
                        <?php if ($pending_requests_result && $pending_requests_result->num_rows > 0): ?>
                            <div style="max-height: 320px; overflow-y: auto;">
                                <?php while($req = $pending_requests_result->fetch_assoc()): ?>
                                    <div class="request-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?= htmlspecialchars($req['facility_name']) ?></strong>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($req['FirstName'] . ' ' . $req['LastName']) ?>
                                                </div>
                                            </div>
                                            <span class="badge-custom bg-warning text-dark">Pending</span>
                                        </div>
                                        <div class="small text-muted">
                                            <?= date('M d, Y', strtotime($req['event_start_date'])) ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <a href="reserverequests.php" class="btn view-all-btn w-100 mt-3">View All Requests</a>
                        <?php else: ?>
                            <div class="alert alert-info">No pending requests</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if ($recent_audit_result && $recent_audit_result->num_rows > 0): ?>
            <div class="activity-feed animate-in" style="animation-delay: 0.6s">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Activity</h3>
                </div>

                <?php while($log = $recent_audit_result->fetch_assoc()): ?>
                <?php
                    $activityClass = 'created';
                    $iconSymbol = 'add_circle';
                    $actionText = 'created';

                    switch($log['ActionType']) {
                        case 'Approved': $activityClass='approved'; $iconSymbol='check_circle'; $actionText='approved'; break;
                        case 'Rejected': $activityClass='rejected'; $iconSymbol='cancel'; $actionText='rejected'; break;
                        case 'Event_Created': $activityClass='created'; $iconSymbol='event'; $actionText='created'; break;
                        case 'Updated': $activityClass='updated'; $iconSymbol='edit'; $actionText='updated'; break;
                    }

                    $adminName = $log['AdminName'] ?? 'System';
                    $residentName = $log['ResidentName'] ?? 'Unknown';
                    $facilityName = $log['FacilityName'] ?? 'Unknown Facility';
                    $eventDate = $log['EventStartDate'] ? date('F d, Y', strtotime($log['EventStartDate'])) : 'N/A';
                    $timeRange = ($log['TimeStart'] && $log['TimeEnd']) ? date('g:i A', strtotime($log['TimeStart'])) . ' - ' . date('g:i A', strtotime($log['TimeEnd'])) : '';
                    $timestamp = date('M d, Y \a\t g:i A', strtotime($log['Timestamp']));
                ?>
                <div class="activity-item <?= $activityClass ?>">
                    <div class="d-flex align-items-start gap-3">
                        <div class="activity-icon <?= $activityClass ?>">
                            <span class="material-symbols-outlined"><?= $iconSymbol ?></span>
                        </div>
                        <div class="activity-content">
                            <div class="activity-main">
                                <?= htmlspecialchars($adminName); ?> <?= $actionText; ?> a reservation request
                            </div>
                            <div class="activity-details">
                                <strong>Resident:</strong> <?= htmlspecialchars($residentName); ?> • 
                                <strong>Facility:</strong> <?= htmlspecialchars($facilityName); ?><br>
                                <strong>Date:</strong> <?= htmlspecialchars($eventDate); ?>
                                <?php if ($timeRange): ?>
                                    • <strong>Time:</strong> <?= htmlspecialchars($timeRange); ?>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">schedule</span>
                                <?= htmlspecialchars($timestamp); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="activity-feed">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Activity</h3>
                </div>
                <div class="alert alert-info mb-0">No recent activity found.</div>
            </div>
            <?php endif; ?>

        </div>

    </div>

    <script>
        // Chart Data
        const chartData = {
            approved: <?php echo $status_data['approved'] ?? 0; ?>,
            rejected: <?php echo $status_data['rejected'] ?? 0; ?>,
            pending: <?php echo $status_data['pending'] ?? 0; ?>
        };

        // Create Chart
        const ctx = document.getElementById('myChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Rejected', 'Pending'],
                datasets: [{
                    data: [chartData.approved, chartData.rejected, chartData.pending],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 206, 86, 0.8)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 14,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>

</body>

</html>

<?php
$conn->close();
?>