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

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current user data
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

$message = "";

// Hide reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("UPDATE reservations SET admin_visible = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// ✅ Reservations query
$res_sql = "SELECT
                r.id,
                r.facility_name,
                r.phone,
                r.event_start_date,
                r.event_end_date,
                r.time_start,
                r.time_end,
                r.status,
                r.note,
                r.updated_at,
                u.FirstName,
                u.LastName
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE r.status IN ('approved','rejected')
              AND r.admin_visible = 1
            ORDER BY r.updated_at DESC, r.id DESC";

$reservations = $conn->query($res_sql);
if (!$reservations) {
    die("SQL Error: ".$conn->error);
}

// ✅ Fetch distinct facilities for filter dropdown
$facility_sql = "SELECT DISTINCT facility_name FROM reservations ORDER BY facility_name ASC";
$facility_list = $conn->query($facility_sql);
$facilities_array = [];
if ($facility_list) {
    while ($f = $facility_list->fetch_assoc()) {
        $facilities_array[] = $f['facility_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approved & Rejected Reservations</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<link rel="stylesheet" href="adminside.css">
<link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
<link rel="stylesheet" href="reservations-filter.css">
<style>
/* Hide ID column */
#reservationTable th.id-column,
#reservationTable td.id-column {
    display: none;
}
</style>
</head>

<body>
<div class="app-layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <header class="sidebar-header">
        <div class="profile-section">
            <img src="<?= $profilePic ?>" alt="Profile" class="profile-photo">
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
                <a href="reservations.php" class="menu-link active">
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
        <a href="log-out.php" class="logout-link">
            <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
            <span class="menu-label">Log Out</span>
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
<div class="reservation-card">
<div class="page-header">Approved & Rejected Reservations</div>
<div class="card-body">

<div class="alert alert-info">
    These are all reservations that have been <strong>approved</strong> or <strong>rejected</strong>.
    You may delete them to remove from this list.
</div>  

<!-- SEARCH + FILTER ROW -->
<div class="search-filter-row">
    <input type="text" id="searchInput" class="form-control search-bar" placeholder="Search...">

    <div class="filter-dropdown">
        <button id="filterButton" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">filter_list</span>
            Filter
        </button>

        <div id="filterMenu" class="filter-menu">
            <div class="filter-section">
                <h6>Facilities</h6>
                <div id="facilityFilters">
                    <?php foreach ($facilities_array as $facility): ?>
                    <div class="filter-option">
                        <input type="checkbox" class="facility-checkbox" value="<?= htmlspecialchars($facility) ?>" id="fac-<?= htmlspecialchars($facility) ?>">
                        <label for="fac-<?= htmlspecialchars($facility) ?>"><?= htmlspecialchars($facility) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filter-section">
                <h6>Status</h6>
                <div class="filter-option">
                    <input type="checkbox" class="status-checkbox" value="approved" id="status-approved">
                    <label for="status-approved">Approved</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" class="status-checkbox" value="rejected" id="status-rejected">
                    <label for="status-rejected">Rejected</label>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-secondary btn-sm" id="clearFilters">Clear All</button>
                <button class="btn btn-primary btn-sm" id="applyFilters">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- RESERVATION TABLE -->
<div class="table-responsive mt-3">
<table class="table table-bordered" id="reservationTable">
<thead class="table-dark">
<tr>
    <th class="id-column">ID</th>
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
<tr class="reservation-row"
    data-facility="<?= htmlspecialchars($row['facility_name']) ?>"
    data-phone="<?= htmlspecialchars($row['phone']) ?>"
    data-user="<?= htmlspecialchars($row['FirstName'].' '.$row['LastName']) ?>"
    data-date="<?= date('M d, Y', strtotime($row['event_start_date'])) ?>"
    data-time="<?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?>"
    data-status="<?= ucfirst($row['status']) ?>"
    data-note="<?= htmlspecialchars($row['note'] ?: 'No notes provided') ?>"
    data-updated="<?= date('M d, Y g:i A', strtotime($row['updated_at'])) ?>"
>
<td class="id-column"><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['facility_name']) ?></td>
<td><?= htmlspecialchars($row['phone']) ?></td>
<td><?= date('M d, Y', strtotime($row['event_start_date'])) ?></td>
<td><?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?></td>
<td><?= htmlspecialchars($row['FirstName'].' '.$row['LastName']) ?></td>
<td>
    <span class="badge <?= $row['status']==='approved'?'bg-success':'bg-danger' ?>">
        <?= ucfirst($row['status']) ?>
    </span>
</td>
<td>
<form method="POST" onsubmit="return confirm('Delete this reservation?')">
<input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
<button type="submit" name="delete_reservation" class="btn btn-danger btn-sm">Delete</button>
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
<div class="modal fade" id="reservationModal" tabindex="-1">
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
<p><strong>Last Updated:</strong> <span id="mUpdated"></span></p>
<hr>
<p><strong>Resident Note:</strong></p>
<p id="mNote" class="text-muted"></p>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal row click -->
<script>
document.querySelectorAll('.reservation-row').forEach(row => {
    row.addEventListener('click', e => {
        if (e.target.closest('form')) return;

        document.getElementById('mFacility').textContent = row.dataset.facility;
        document.getElementById('mUser').textContent = row.dataset.user;
        document.getElementById('mPhone').textContent = row.dataset.phone;
        document.getElementById('mDate').textContent = row.dataset.date;
        document.getElementById('mTime').textContent = row.dataset.time;
        document.getElementById('mStatus').textContent = row.dataset.status;
        document.getElementById('mNote').textContent = row.dataset.note;
        document.getElementById('mUpdated').textContent = row.dataset.updated;

        new bootstrap.Modal(document.getElementById('reservationModal')).show();
    });
});
</script>

<!-- Dropdown Filter -->
<script>
const filterButton = document.getElementById('filterButton');
const filterMenu = document.getElementById('filterMenu');
const applyButton = document.getElementById('applyFilters');
const clearButton = document.getElementById('clearFilters');

// Toggle dropdown
filterButton.addEventListener('click', function(e) {
    e.stopPropagation();
    filterMenu.classList.toggle('show');
    
    if (filterMenu.classList.contains('show')) {
        const rect = filterButton.getBoundingClientRect();
        filterMenu.style.top = (rect.bottom + 5) + 'px';
        filterMenu.style.left = (rect.right - 280) + 'px';
    }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!filterMenu.contains(e.target) && e.target !== filterButton) {
        filterMenu.classList.remove('show');
    }
});

// Prevent closing on click inside
filterMenu.addEventListener('click', function(e) { e.stopPropagation(); });

// Apply filters
applyButton.addEventListener('click', function() {
    let selectedFacilities = [];
    document.querySelectorAll('.facility-checkbox:checked').forEach(cb => selectedFacilities.push(cb.value));

    let selectedStatuses = [];
    document.querySelectorAll('.status-checkbox:checked').forEach(cb => selectedStatuses.push(cb.value));

    document.querySelectorAll("#reservationTable tbody tr").forEach(row => {
        let facility = row.children[1].textContent.trim();
        let status = row.children[6].textContent.trim().toLowerCase();
        row.style.display = (selectedFacilities.length === 0 || selectedFacilities.includes(facility)) &&
                            (selectedStatuses.length === 0 || selectedStatuses.includes(status)) ? '' : 'none';
    });

    filterMenu.classList.remove('show');
});

// Clear filters
clearButton.addEventListener('click', function() {
    document.querySelectorAll('.facility-checkbox, .status-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll("#reservationTable tbody tr").forEach(row => row.style.display = '');
    filterMenu.classList.remove('show');
});
</script>

<!-- Search -->
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let search = this.value.toLowerCase();
    document.querySelectorAll("#reservationTable tbody tr").forEach(row => {
        let facility = row.children[1].textContent.toLowerCase();
        let phone = row.children[2].textContent.toLowerCase();
        let user = row.children[5].textContent.toLowerCase();
        let status = row.children[6].textContent.toLowerCase();
        row.style.display = (facility.includes(search) || phone.includes(search) || user.includes(search) || status.includes(search)) ? '' : 'none';
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>
