<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    header("Location: ../login/login.php");
    exit();
}

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

// Get current user's reservations (include pending, approved, rejected - exclude completed if you want)
$user_id = $_SESSION['user_id'];

$query = "SELECT facility_name, event_start_date, time_start, time_end, status, created_at 
          FROM reservations 
          WHERE user_id = :user_id 
          AND status IN ('approved', 'rejected', 'pending')
          ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC";
          
$stmt = $conn->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../resident-side/make-reservation.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <title>My Reservations - Facility Reservation System</title>
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <header class="sidebar-header">
            <img src="../asset/logo.png" alt="Header Logo" class="header-logo">
            <button class="sidebar-toggle">
                <span class="material-symbols-outlined">
                    chevron_left
                </span>
            </button>
        </header>

        <div class="sidebar-content">
            <ul class="menu-list">
                <li class="menu-item">
                    <a href="../home/home.php" class="menu-link">
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
                    <a href="myreservations.php" class="menu-link active">
                        <img src="../asset/reservations.png" class="menu-icon">
                        <span class="menu-label">My Reservations</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <img src="../asset/bell.png" class="menu-icon">
                        <span class="menu-label">My Balance</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <img src="../asset/profile.png" class="menu-icon">
                        <span class="menu-label">My Account</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="reservation-card">
            <div class="page-header">
                My Reservations
            </div>

            <div class="card-body">
                <div class="alert alert-info mb-4">
                    Showing <strong>pending</strong>, <strong>approved</strong>, and <strong>rejected</strong> reservations. Completed reservations are not displayed.
                </div>

                <div class="table-responsive">

                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Facility</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time Slot</th>
                                <th scope="col">Status</th>
                                <th scope="col">Booked On</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reservations) > 0): ?>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['facility_name']); ?></td>
                                        <td><?php echo date('F d, Y', strtotime($reservation['event_start_date'])); ?></td>
                                        <td>
                                            <?php 
                                                echo date('g:i A', strtotime($reservation['time_start'])) . 
                                                     ' - ' . 
                                                     date('g:i A', strtotime($reservation['time_end']));
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = match($reservation['status']) {
                                                    'approved' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white',
                                                    'completed' => 'bg-secondary text-white',
                                                    'pending' => 'bg-warning text-dark',
                                                    default => 'bg-dark text-white'
                                                };
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger delete-btn">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="alert alert-info mb-0">
                                            <h5 class="alert-heading mb-3">No reservations found!</h5>
                                            <p>You currently have no reservations to display.
                                            <a href="../resident-side/make-reservation.php" class="alert-link fw-bold">
                                                Make a reservation now!
                                            </a></p>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../resident-side/javascript/sidebar.js"></script>

<!--  SWAL Inline JS for Delete Button -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-btn');

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = btn.closest('tr');

            Swal.fire({
                title: "Remove this reservation?",
                text: "This will only remove it from view.",
                icon: "warning",
                showDenyButton: true,
                showCancelButton: false,
                confirmButtonText: "Remove",
                denyButtonText: "Keep",
            }).then((result) => {
                if (result.isConfirmed) {
                    row.style.display = 'none';

                    Swal.fire("Removed!", "Reservation hidden successfully.", "success");
                } 
                else if (result.isDenied) {
                    Swal.fire("Not removed", "Reservation is still visible.", "info");
                }
            });

        });
    });
});
</script>

<!-- SWAL IMPORT LINK -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
