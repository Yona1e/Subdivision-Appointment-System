<?php
session_start();

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch only active residents from database - Query the 'users' table
$sql = "SELECT user_id, Email, Role, FirstName, LastName, Status FROM users WHERE Role = 'Resident' AND Status = 'Active' ORDER BY user_id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Active Residents</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="admin.css">

</head>
<body>
<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <header class="sidebar-header">
            <img src="../asset/logo.png" alt="Header Logo" class="header-logo">
            <button class="sidebar-toggle">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
        </header>

        <div class="sidebar-content">
            <!-- Menu List -->
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
                    <a href="#" class="menu-link">
                        <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                        <span class="menu-label">Create Account</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="reservation-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Admin Dashboard - Active Residents</h1>
                    <p class="text-muted">Logged in as: <?php echo $_SESSION['firstName'] . ' ' . $_SESSION['lastName']; ?> (Admin)</p>
                </div>
                <form action ="log-out.php" method="post">
                    <button type="submit" class="btn btn-danger">Logout</button>
                </form>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Total Active Residents: <?php echo $result->num_rows; ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Display only active residents from the 'users' table -->
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>User ID</th>
                                    <th>Email</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo $user['Email']; ?></td>
                                        <td><?php echo $user['FirstName']; ?></td>
                                        <td><?php echo $user['LastName']; ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $user['Status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <!-- End of active residents table display -->
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No active residents found in the database.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
<script src="../resident-side/javascript/sidebar.js"></script>
</body>
</html>
<?php
$conn->close();
?>