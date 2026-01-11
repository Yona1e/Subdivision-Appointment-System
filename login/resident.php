<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <!-- Your Custom CSS -->
    <link rel="stylesheet" href="../test/test.css">
</head>
<body>
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
                    <a href="resident.php" class="menu-link active">
                        <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                        <span class="menu-label">Home</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="make_reservation.php" class="menu-link">
                        <img src="../asset/makeareservation.png" alt="Make a Reservation Icon" class="menu-icon">
                        <span class="menu-label">Make a Reservation</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reservations.php" class="menu-link">
                        <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                        <span class="menu-label">Reservations</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="my_balance.php" class="menu-link">
                        <img src="../asset/bell.png" alt="My Balance Icon" class="menu-icon">
                        <span class="menu-label">My Balance</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="my_account.php" class="menu-link">
                        <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                        <span class="menu-label">My Account</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Welcome, <?php echo $_SESSION['firstName'] . ' ' . $_SESSION['lastName']; ?>!</h1>
                <p class="text-muted">Resident Dashboard</p>
            </div>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Your Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>User ID</th>
                            <th>Generated ID</th>
                            <th>Role</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $_SESSION['userID']; ?></td>
                            <td><?php echo $_SESSION['generatedID']; ?></td>
                            <td><?php echo $_SESSION['role']; ?></td>
                            <td><?php echo $_SESSION['firstName']; ?></td>
                            <td><?php echo $_SESSION['lastName']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });
        }
    });
</script>
</body>
</html>