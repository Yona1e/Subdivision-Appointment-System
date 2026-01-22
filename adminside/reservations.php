<?php
session_start();

/* Prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

/* DB */
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Admin info */
$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../'.$user['ProfilePictureURL']))
    ? '../'.$user['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($user['FirstName'].' '.$user['LastName']);

/* Hide reservation */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("UPDATE reservations SET admin_visible = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

/* Reservations */
$res_sql = "
SELECT
    r.id,
    r.facility_name,
    r.phone,
    r.event_start_date,
    r.time_start,
    r.time_end,
    r.status,
    r.note,
    r.updated_at,
    r.payment_proof,
    u.FirstName,
    u.LastName
FROM reservations r
LEFT JOIN users u ON r.user_id = u.user_id
WHERE r.status IN ('approved','rejected')
AND r.admin_visible = 1
ORDER BY r.updated_at DESC, r.id DESC";

$reservations = $conn->query($res_sql);

/* Facilities */
$facility_sql = "SELECT DISTINCT facility_name FROM reservations ORDER BY facility_name ASC";
$facility_list = $conn->query($facility_sql);
$facilities_array = [];
while ($f = $facility_list->fetch_assoc()) {
    $facilities_array[] = $f['facility_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approved & Rejected Reservations</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<link rel="stylesheet" href="adminside.css">
<link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
<link rel="stylesheet" href="reservations-filter.css">
<style>
#reservationTable th.id-column,
#reservationTable td.id-column { display:none; }

.payment-proof-img {
    width: 100%;
    max-height: 350px;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid #ddd;
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
                        <a href="reservations.php" class="menu-link  active">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
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


<!-- MAIN -->
<div class="main-content">
<div class="reservation-card">
<div class="page-header">Approved & Rejected Reservations</div>
<div class="card-body">

<div class="alert alert-info">
Approved and rejected reservations. Click a row to view full details.
</div>

<div class="table-responsive">
<table class="table table-bordered" id="reservationTable">
<thead class="table-dark">
<tr>
    <th class="id-column">ID</th>
    <th>Facility</th>
    <th>Phone</th>
    <th>Date</th>
    <th>Time</th>
    <th>User</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php while ($row = $reservations->fetch_assoc()): ?>
<tr class="reservation-row"
    data-facility="<?= htmlspecialchars($row['facility_name']) ?>"
    data-user="<?= htmlspecialchars($row['FirstName'].' '.$row['LastName']) ?>"
    data-phone="<?= htmlspecialchars($row['phone']) ?>"
    data-date="<?= date('M d, Y', strtotime($row['event_start_date'])) ?>"
    data-time="<?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?>"
    data-status="<?= ucfirst($row['status']) ?>"
    data-note="<?= htmlspecialchars($row['note'] ?: 'No notes provided') ?>"
    data-updated="<?= date('M d, Y g:i A', strtotime($row['updated_at'])) ?>"
    data-payment="<?= htmlspecialchars($row['payment_proof']) ?>"
>
<td class="id-column"><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['facility_name']) ?></td>
<td><?= htmlspecialchars($row['phone']) ?></td>
<td><?= date('M d, Y', strtotime($row['event_start_date'])) ?></td>
<td><?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?></td>
<td><?= htmlspecialchars($row['FirstName'].' '.$row['LastName']) ?></td>
<td>
<span class="badge <?= $row['status']=='approved'?'bg-success':'bg-danger' ?>">
<?= ucfirst($row['status']) ?>
</span>
</td>
<td>
<form method="POST">
<input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
<button name="delete_reservation" class="btn btn-danger btn-sm">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</div>
</div>
</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="reservationModal">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Reservation Details</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<p><strong>Facility:</strong> <span id="mFacility"></span></p>
<p><strong>User:</strong> <span id="mUser"></span></p>
<p><strong>Phone:</strong> <span id="mPhone"></span></p>
<p><strong>Date:</strong> <span id="mDate"></span></p>
<p><strong>Time:</strong> <span id="mTime"></span></p>
<p><strong>Status:</strong> <span id="mStatus"></span></p>
<p><strong>Updated:</strong> <span id="mUpdated"></span></p>

<hr>
<p><strong>Resident Note:</strong></p>
<p id="mNote" class="text-muted"></p>

<hr>
<p><strong>Payment Proof:</strong></p>
<div id="paymentContainer" class="text-center text-muted">No payment proof uploaded</div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.reservation-row').forEach(row => {
    row.addEventListener('click', e => {
        if (e.target.closest('form')) return;

        mFacility.textContent = row.dataset.facility;
        mUser.textContent = row.dataset.user;
        mPhone.textContent = row.dataset.phone;
        mDate.textContent = row.dataset.date;
        mTime.textContent = row.dataset.time;
        mStatus.textContent = row.dataset.status;
        mUpdated.textContent = row.dataset.updated;
        mNote.textContent = row.dataset.note;

        const payment = row.dataset.payment;
        const container = document.getElementById('paymentContainer');

        if (payment) {
            container.innerHTML = `<img src="../${payment}" class="payment-proof-img">`;
        } else {
            container.textContent = "No payment proof uploaded";
        }

        new bootstrap.Modal(document.getElementById('reservationModal')).show();
    });
});
</script>
<script src="../resident-side/javascript/sidebar.js"></script>
</body>
</html>
<?php $conn->close(); ?>
