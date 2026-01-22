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
$today = date('Y-m-d');

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

$nextReservation = $conn->prepare(
    "SELECT facility_name, event_start_date, time_start
     FROM reservations
     WHERE user_id = ? AND status='approved'
     AND CONCAT(event_start_date,' ',time_start) >= NOW()
     ORDER BY event_start_date, time_start
     LIMIT 1"
);
$nextReservation->execute([$user_id]);
$nextReservation = $nextReservation->fetch(PDO::FETCH_ASSOC);

/* TODAY'S NOTIFICATIONS */
$todayNotificationsStmt = $conn->prepare("
    SELECT facility_name, status, time_start, time_end
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
                        <p class="profile-role">Resident</p>
                    </div>
                </div>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="../home/home.php" class="menu-link active">
                            <img src="../asset/home.png" class="menu-icon">
                            <span class="menu-label">Home</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../resident-side/make-reservation.php" class="menu-link">
                            <img src="../asset/makeareservation.png" class="menu-icon">
                            <span class="menu-label">Make a Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../my-reservations/myreservations.php" class="menu-link">
                            <img src="../asset/reservations.png" class="menu-icon">
                            <span class="menu-label">My Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../my-account/my-account.php" class="menu-link">
                            <img src="../asset/profile.png" class="menu-icon">
                            <span class="menu-label">My Account</span>
                        </a>
                    </li>
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

<h1 class="mb-4 mt-3">Welcome Back, <?= htmlspecialchars($user['FirstName']) ?></h1>

<!-- STATS CARD -->
<div class="row g-4 mb-4">

    <div class="col-lg-4 col-md-6 col-12">
        <div class="reservation-card p-4 h-100 w-100 text-center">
            <span class="d-block fs-2 fw-bold"><?= $totalReservations ?></span>
            <p class="mb-0 text-muted">Total Reservations</p>
        </div>
    </div>

    <div class="col-lg-4 col-md-6 col-12">
        <div class="reservation-card p-4 h-100 w-100 text-center">
            <span class="d-block fs-2 fw-bold"><?= $upcomingReservations ?></span>
            <p class="mb-0 text-muted">Upcoming Reservations</p>
        </div>
    </div>

    <div class="col-lg-4 col-md-6 col-12">
        <div class="reservation-card p-4 h-100 w-100 text-center">
            <span class="d-block fs-2 fw-bold"><?= $facilitiesToday ?></span>
            <p class="mb-0 text-muted">Facilities In Use Today</p>
        </div>
    </div>

</div>




<!-- NOTIFICATIONS -->
<div class="reservation-card mb-4" style="padding:10px 20px;">
<div class="card-body">
<h4 class="section-title" style="padding-top: 6px; padding-left: 6px;">Your Notifications For Today</h4>

<?php if ($todayNotifications): ?>
    <ul class="time-list">
        <?php foreach ($todayNotifications as $n): ?>
            <li style="
    background: <?= $n['status'] === 'approved'
       ? 'rgb(40, 167, 69)'         /* solid green outline */
        : 'rgb(220, 53, 69)' ?>;     /* solid red outline */
    color: White;
    text-align: left;
    padding: 14px 16px;
    border-radius: 10px;
">
                <strong><?= htmlspecialchars($n['facility_name']) ?></strong><br>
                <?= date('g:i A', strtotime($n['time_start'])) ?> – <?= date('g:i A', strtotime($n['time_end'])) ?><br>
                <span style="font-weight:600;">
                    <?= ucfirst($n['status']) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="no-data">No approval or rejection updates today.</p>
<?php endif; ?>

</div>
</div>

<!-- FACILITY SCHEDULE -->
<div class="reservation-card mb-4" style="padding:10px 20px;">
<div class="card-body">
<h4 class="section-title">Today's Facility Schedule (All Residents)</h4>
<div class="facility-grid" id="facilityContainer"></div>
</div>
</div>

</div>
</div>

<script src="../resident-side/javascript/sidebar.js"></script>

<script>
$(function () {
    const now = new Date();
const today = now.getFullYear() + '-' +
    String(now.getMonth() + 1).padStart(2, '0') + '-' +
    String(now.getDate()).padStart(2, '0');

    $.getJSON('display_event.php', res => {
        if (!res.status) return;

        const grouped = {};
        res.data.forEach(e => {
            if (e.start.startsWith(today)) {
                grouped[e.title] ??= [];
                grouped[e.title].push(e);
            }
        });

        Object.keys(grouped).slice(0,4).forEach(facility => {
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
        return new Date(dt).toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit'
        });
    }
});
</script>

</body>
</html>
