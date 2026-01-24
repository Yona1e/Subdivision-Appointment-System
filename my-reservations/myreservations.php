<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    header("Location: ../login/login.php");
    exit();
}

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch current user data for sidebar
$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Profile picture fallback
$profilePic = !empty($user['ProfilePictureURL'])
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);

// Handle AJAX delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hide_reservation') {
    header('Content-Type: application/json');

    $reservation_id = $_POST['reservation_id'] ?? null;

    if ($reservation_id) {
        $stmt = $conn->prepare(
            "UPDATE reservations 
             SET resident_visible = FALSE 
             WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([
            ':id' => $reservation_id,
            ':user_id' => $user_id
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Fetch reservations
$query = "SELECT id, facility_name, event_start_date, time_start, time_end, status, reason, created_at
          FROM reservations
          WHERE user_id = :user_id
          AND status IN ('approved','rejected','pending')
          AND resident_visible = TRUE
          ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="myreservations.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">

    <title>My Reservations</title>
</head>

<body>

    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <a href="../my-account/my-account.php" class="profile-link">
                <div class="profile-section">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-photo">
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
                    <li class="menu-item"><a href="../home/home.php" class="menu-link"><img src="../asset/home.png"
                                class="menu-icon">Home</a></li>
                    <li class="menu-item"><a href="../resident-side/make-reservation.php" class="menu-link"><img
                                src="../asset/makeareservation.png" class="menu-icon">Make a Reservation</a></li>
                    <li class="menu-item"><a href="myreservations.php" class="menu-link active"><img
                                src="../asset/reservations.png" class="menu-icon">My Reservations</a></li>
                    <li class="menu-item"><a href="../my-account/my-account.php" class="menu-link"><img
                                src="../asset/profile.png" class="menu-icon">My Account</a></li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="../adminside/log-out.php" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" class="menu-icon">Log Out
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="reservation-card">

                <div class="page-header">My Reservations</div>

                <div class="card-body">
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            Showing <strong>pending</strong>, <strong>approved</strong>, and <strong>rejected</strong>
                            reservations. Completed reservations are not displayed.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Facility</th>
                                        <th>Date</th>
                                        <th>Time Slot</th>
                                        <th>Status</th>
                                        <th>Booked On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php foreach ($reservations as $reservation): ?>
                                    <tr data-reservation-id="<?= $reservation['id'] ?>">
                                        <td>
                                            <?= htmlspecialchars($reservation['facility_name']) ?>
                                        </td>
                                        <td>
                                            <?= date('F d, Y', strtotime($reservation['event_start_date'])) ?>
                                        </td>
                                        <td>
                                            <?= date('g:i A', strtotime($reservation['time_start'])) ?> -
                                            <?= date('g:i A', strtotime($reservation['time_end'])) ?>
                                        </td>
                                        <td>
                                            <?php
$statusClass = match($reservation['status']) {
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
    'pending' => 'bg-warning text-dark',
    default => 'bg-secondary'
};
?>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= ucfirst($reservation['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y g:i A', strtotime($reservation['created_at'])) ?>
                                        </td>

                                        <td>
                                            <div class="btn-group-actions">

                                                <?php if ($reservation['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>

                                                <?php else: ?>

                                                <?php if ($reservation['status'] === 'rejected'): ?>
                                                <button type="button" class="btn btn-sm btn-warning view-reason-btn"
                                                    data-reason="<?= htmlspecialchars($reservation['reason'] ?? 'No reason provided.') ?>">
                                                    <i class="bi bi-eye"></i> View Reason
                                                </button>
                                                <?php endif; ?>

                                                <button class="btn btn-sm btn-outline-danger delete-btn">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>

                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- REJECTION MODAL -->
        <div class="modal fade" id="rejectionReasonModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rejection Reason</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="rejectionReasonText"></p>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../resident-side/javascript/sidebar.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            document.addEventListener('DOMContentLoaded', () => {

                document.querySelectorAll('.view-reason-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('rejectionReasonText').textContent = btn.dataset.reason;
                        new bootstrap.Modal(document.getElementById('rejectionReasonModal')).show();
                    });
                });

                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('tr');
                        const id = row.dataset.reservationId;

                        Swal.fire({
                            title: "Remove this reservation?",
                            icon: "warning",
                            showDenyButton: true,
                            confirmButtonText: "Remove",
                            denyButtonText: "Keep"
                        }).then(result => {
                            if (result.isConfirmed) {
                                fetch('myreservations.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `action=hide_reservation&reservation_id=${id}`
                                }).then(() => row.remove());
                            }
                        });
                    });
                });

            });
        </script>

</body>

</html>