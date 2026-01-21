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

// Updated query to include note and payment_proof
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
                r.created_at,
                r.payment_proof,
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
    <style>
    .reservation-row {
        cursor: pointer;
    }
    .reservation-row:hover {
        background-color: #f8f9fa;
    }
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
                    <a href="reserverequests.php" class="menu-link active">
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
                    <a href="manageaccounts" class="menu-link">
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
            <a  href="log-out.php" method="post" class="logout-link menu-link">
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

                <div class="alert alert-info">
                    Pending reservations awaiting approval. Click a row to view full details.
                </div>

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
                                <tr class="reservation-row"
                                    data-facility="<?= htmlspecialchars($row['facility_name']) ?>"
                                    data-user="<?= htmlspecialchars($row['FirstName'].' '.$row['LastName']) ?>"
                                    data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                    data-date="<?= date('M d, Y', strtotime($row['event_start_date'])) ?><?php if ($row['event_start_date'] != $row['event_end_date']) echo ' - ' . date('M d, Y', strtotime($row['event_end_date'])); ?>"
                                    data-time="<?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?>"
                                    data-status="<?= ucfirst($row['status']) ?>"
                                    data-note="<?= htmlspecialchars($row['note'] ?: 'No notes provided') ?>"
                                    data-created="<?= date('M d, Y g:i A', strtotime($row['created_at'])) ?>"
                                    data-payment="<?= htmlspecialchars($row['payment_proof']) ?>"
                                >
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
                                    <td><span class="badge bg-warning text-white"><?php echo ucfirst($row['status']); ?></span></td>
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
<p><strong>Created:</strong> <span id="mCreated"></span></p>

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
        mCreated.textContent = row.dataset.created;
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