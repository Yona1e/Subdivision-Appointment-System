<?php
session_start();
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login/login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$conn = new PDO(
    "mysql:host=localhost;dbname=facilityreservationsystem;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$conn->exec("SET time_zone = '+08:00'");
$user_id = $_SESSION['user_id'];

/* Sidebar user */
$stmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL']))
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);

/* STATISTICS */
$totalReservations = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
$totalReservations->execute([$user_id]);
$totalReservations = $totalReservations->fetchColumn();

$upcomingReservations = $conn->prepare(
    "SELECT COUNT(*) FROM reservations 
     WHERE user_id = ? AND status='approved' AND event_start_date >= ?"
);
$upcomingReservations->execute([$user_id, $today]);
$upcomingReservations = $upcomingReservations->fetchColumn();

$facilitiesToday = $conn->query(
    "SELECT COUNT(DISTINCT facility_name)
     FROM reservations
     WHERE status='approved' AND event_start_date='$today'"
)->fetchColumn();

/* TODAY'S NOTIFICATIONS */
$todayNotificationsStmt = $conn->prepare("
    SELECT facility_name, status, time_start, time_end, reason
    FROM reservations
    WHERE user_id = ?
      AND status IN ('approved','rejected')
      AND DATE(updated_at) = ?
    ORDER BY updated_at DESC
");
$todayNotificationsStmt->execute([$user_id, $today]);
$todayNotifications = $todayNotificationsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Resident Home</title>

    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <style>
        /* alignment fix */
        .reservation-card {
            text-align: left;
        }

        .stat-card {
            text-align: center;
        }

        /* === UIVERSE NOTIFICATION CARDS (END-TO-END FIX) === */
        .notify-card {
            width: 100%;
            min-height: 80px;
            border-radius: 8px;
            padding: 12px 16px;
            background: #fff;
            box-shadow: rgba(149, 157, 165, .2) 0 8px 24px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notify-card .wave {
            position: absolute;
            transform: rotate(90deg);
            left: -32px;
            top: 32px;
            width: 80px;
        }

        .notify-approved .wave {
            fill: #04e4003a;
        }

        .notify-rejected .wave {
            fill: #ff000033;
        }

        .icon-container {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .notify-approved .icon-container {
            background: #04e40048;
        }

        .notify-rejected .icon-container {
            background: #ff000044;
        }

        .notify-approved .message-text {
            color: #269b24;
        }

        .notify-rejected .message-text {
            color: #c0392b;
        }

        .message-text {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
        }

        .sub-text {
            font-size: 14px;
            color: #555;
        }

        /* === HORIZONTAL SCROLL FOR FACILITY CARDS === */
        .facility-grid {
            display: flex !important;
            gap: 20px;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 15px;
            scroll-behavior: smooth;
        }

        /* Custom scrollbar styling */
        .facility-grid::-webkit-scrollbar {
            height: 8px;
        }

        .facility-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .facility-grid::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .facility-grid::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .facility-card {
            min-width: 280px !important;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .facility-card {
                min-width: 250px !important;
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <a href="../my-account/my-account.php" class="profile-link">
                    <div class="profile-section">
                        <img src="<?= htmlspecialchars($profilePic) ?>" class="profile-photo">
                        <div class="profile-info">
                            <p class="profile-name">
                                <?= $userName ?>
                            </p>
                            <p class="profile-role">Resident</p>
                        </div>
                    </div>
                </a>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <ul class="menu-list">
                    <li class="menu-item"><a href="../home/home.php" class="menu-link active"><img
                                src="../asset/home.png" class="menu-icon">Home</a></li>
                    <li class="menu-item"><a href="../resident-side/make-reservation.php" class="menu-link"><img
                                src="../asset/makeareservation.png" class="menu-icon">Make a Reservation</a></li>
                    <li class="menu-item"><a href="../my-reservations/myreservations.php" class="menu-link"><img
                                src="../asset/reservations.png" class="menu-icon">My Reservations</a></li>
                    <li class="menu-item"><a href="../my-account/my-account.php" class="menu-link"><img
                                src="../asset/profile.png" class="menu-icon">My Account</a></li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="../adminside/log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">

            <h1 class="mb-4 mt-3">Welcome Back,
                <?= htmlspecialchars($user['FirstName']) ?>
            </h1>

            <!-- STATS -->
            <div class="row g-5 mb-4">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="reservation-card stat-card p-4 h-100">
                        <span class="fs-2 fw-bold">
                            <?= $totalReservations ?>
                        </span>
                        <p class="text-muted mb-0">Total Reservations</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="reservation-card stat-card p-4 h-100">
                        <span class="fs-2 fw-bold">
                            <?= $upcomingReservations ?>
                        </span>
                        <p class="text-muted mb-0">Upcoming Reservations</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="reservation-card stat-card p-4 h-100">
                        <span class="fs-2 fw-bold">
                            <?= $facilitiesToday ?>
                        </span>
                        <p class="text-muted mb-0">Facilities In Use Today</p>
                    </div>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="card recent-activity-card mt-4" style="margin-bottom: 10px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="section-title" style="margin: 0;">Your Notifications For Today</h4>
                </div>
                <?php if ($todayNotifications): ?>
                    <div class="card-body p-3">
                <div class="d-flex flex-column gap-3 mt-2">

                    <?php foreach ($todayNotifications as $n): ?>
                    <div class="notify-card <?= $n['status']==='approved' ? 'notify-approved' : 'notify-rejected' ?>">

                        <svg class="wave" viewBox="0 0 1440 320">
                            <path d="M0,256L1440,64L1440,320L0,320Z" />
                        </svg>

                        <div class="icon-container">
                            <?= $n['status']==='approved' ? '✔' : '✖' ?>
                        </div>

                        <div>
                            <p class="message-text">
                                <?= ucfirst($n['status']) ?>
                            </p>
                            <p class="sub-text">
                                <?= htmlspecialchars($n['facility_name']) ?><br>
                                <?= date('g:i A', strtotime($n['time_start'])) ?> –
                                <?= date('g:i A', strtotime($n['time_end'])) ?>
                                <?php if ($n['status']==='rejected' && $n['reason']): ?>
                                <br>Reason:
                                <?= htmlspecialchars($n['reason']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                    </div>
                    <?php endforeach; ?>

                </div>

            </div>
                <?php else: ?>
                <p class="no-data">No approval or rejection updates today.</p>
                <?php endif; ?>
            </div>

            <!-- FACILITY SCHEDULE -->
            <div class="card recent-activity-card mt-4" style="margin-bottom: 10px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="section-title" style="margin: 0;">Today's Facility Schedule (All Residents)</h4>
                </div>
            <div class="card-body p-3">
                <div class="facility-grid" id="facilityContainer"></div>
            </div>
        </div>
    </div>

    <script src="../resident-side/javascript/sidebar.js"></script>

    <script>
        $(function () {
            const today = new Date().toISOString().split('T')[0];

            $.getJSON('display_event.php', res => {
                if (!res.status) return;

                const grouped = {};
                res.data.forEach(e => {
                    if (e.start.startsWith(today)) {
                        grouped[e.title] ??= [];
                        grouped[e.title].push(e);
                    }
                });

                // Remove the slice(0, 4) to show all facilities with scrolling
                Object.keys(grouped).forEach(facility => {
                    let slots = grouped[facility].map(e =>
                        `<li>${format(e.start)} – ${format(e.end)}</li>`
                    ).join('');

                    $('#facilityContainer').append(`
<div class="facility-card">
<h5>${facility}</h5>
<ul class="time-list">
${slots || '<li class="no-data">No bookings today</li>'}
</ul>
</div>
`);
                });
            });

            function format(dt) {
                return new Date(dt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            }
        });
    </script>
    </div>
</body>

</html>