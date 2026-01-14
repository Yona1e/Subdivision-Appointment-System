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

// Get current user's reservations (excluding completed)
$user_id = $_SESSION['user_id'];

$query = "SELECT facility_name, event_start_date, time_start, time_end, status, created_at 
          FROM reservations 
          WHERE user_id = :user_id 
          AND status != 'completed'
          ORDER BY created_at DESC";

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
                <div class="table-responsive">

                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Facility</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time Slot</th>
                                <th scope="col">Status</th>
                                <th scope="col">Booked On</th>
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
                                                    'pending' => 'bg-warning text-dark',
                                                    'approved' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white',
                                                    'cancelled' => 'bg-secondary text-white',
                                                    default => 'bg-dark text-white'
                                                };
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>

                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="alert alert-info mb-0">
                                            <h5 class="alert-heading mb-3">No reservations found!</h5>
                                            <p>You have no pending or approved reservations.
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../resident-side/javascript/sidebar.js"></script>
</body>
</html>
