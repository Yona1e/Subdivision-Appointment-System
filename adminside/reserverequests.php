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

// Fetch reservation requests with user details
$sql = "SELECT 
            r.ReservationID,
            r.UserID,
            u.GeneratedID,
            u.FirstName,
            u.LastName,
            r.FacilityID,
            r.ReservationDate,
            r.StartTime,
            r.EndTime,
            r.Purpose,
            r.Status,
            r.RequestDate
        FROM reservations r
        INNER JOIN users u ON r.UserID = u.UserID
        WHERE r.Status = 'Pending'
        ORDER BY r.RequestDate DESC, r.ReservationDate ASC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reservation Requests</title>
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
                    <a href="reserverequests.php" class="menu-link active">
                        <img src="../asset/makeareservation.png" alt="View Requests Icon" class="menu-icon">
                        <span class="menu-label">View Requests</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <img src="../asset/reservations.png" alt="Active Residents Icon" class="menu-icon">
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
                    <h1>Reservation Requests</h1>
                    <p class="text-muted">Logged in as: <?php echo $_SESSION['firstName'] . ' ' . $_SESSION['lastName']; ?> (Admin)</p>
                </div>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Pending Requests: <?php echo $result->num_rows; ?></h5>
                        <div>
                            <span class="badge bg-warning text-dark">Pending Approval</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Resident ID</th>
                                        <th>Resident Name</th>
                                        <th>Facility ID</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($request = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $request['ReservationID']; ?></td>
                                            <td><?php echo $request['GeneratedID']; ?></td>
                                            <td><?php echo $request['FirstName'] . ' ' . $request['LastName']; ?></td>
                                            <td><?php echo $request['FacilityID']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['ReservationDate'])); ?></td>
                                            <td>
                                                <?php 
                                                    echo date('g:i A', strtotime($request['StartTime'])) . ' - ' . 
                                                         date('g:i A', strtotime($request['EndTime'])); 
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['Purpose']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['RequestDate'])); ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $request['Status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="updateStatus(<?php echo $request['ReservationID']; ?>, 'Approved')">
                                                        <span class="material-symbols-outlined" style="font-size: 16px;">check</span>
                                                        Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="updateStatus(<?php echo $request['ReservationID']; ?>, 'Rejected')">
                                                        <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                                                        Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">info</span>
                    No pending reservation requests at this time.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
<script src="../resident-side/javascript/sidebar.js"></script>

<script>
function updateStatus(reservationId, newStatus) {
    if (confirm('Are you sure you want to ' + newStatus.toLowerCase() + ' this reservation request?')) {
        // Create form data
        const formData = new FormData();
        formData.append('reservation_id', reservationId);
        formData.append('status', newStatus);
        
        // Send AJAX request
        fetch('update_reservation_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reservation request ' + newStatus.toLowerCase() + ' successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error('Error:', error);
        });
    }
}
</script>

</body>
</html>
<?php
$conn->close();
?>