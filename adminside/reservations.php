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

$message = "";

// Handle hide reservation (update admin_visible to FALSE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("UPDATE reservations SET admin_visible = FALSE WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    if ($stmt->execute()) {
        $message = "Reservation #$reservation_id has been hidden from view.";
    } else {
        $message = "Error hiding reservation: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch approved & rejected reservations
$res_sql = "SELECT
                r.id,
                r.facility_name,
                r.phone,
                r.event_start_date,
                r.event_end_date,
                r.time_start,
                r.time_end,
                r.status,
                u.FirstName,
                u.LastName
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE LOWER(r.status) IN ('approved', 'rejected')
              AND r.admin_visible = TRUE
            ORDER BY r.status DESC, r.id DESC";

$reservations = $conn->query($res_sql);

// Fetch facilities for checkboxes
$facility_sql = "SELECT DISTINCT facility_name FROM reservations ORDER BY facility_name ASC";
$facility_list = $conn->query($facility_sql);
$facilities_array = [];
while($f = $facility_list->fetch_assoc()) { $facilities_array[] = $f['facility_name']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="adminside.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
</head>

<body>
    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <div class="profile-section">
                    <img src="../asset/profile.jpg" alt="Profile" class="profile-photo">
                    <div class="profile-info">
                        <p class="profile-name">Name</p>
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
                <a href="log-out.php" method="post" class="logout-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="reservation-card">
                <div class="page-header">
                    Approved & Rejected Reservations
                </div>
                <div class="card-body">

                    <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <h5><?php echo $message; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

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
                                            <input type="checkbox" class="facility-checkbox" value="<?php echo htmlspecialchars($facility); ?>" id="fac-<?php echo htmlspecialchars($facility); ?>">
                                            <label for="fac-<?php echo htmlspecialchars($facility); ?>"><?php echo htmlspecialchars($facility); ?></label>
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

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered" id="reservationTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
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
                                <tr>
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
                                    <td><?php echo date('g:i A', strtotime($row['time_start'])) . " - " . date('g:i A', strtotime($row['time_end'])); ?></td>
                                    <td><?php echo $row['FirstName'] . " " . $row['LastName']; ?></td>
                                    <td>
                                        <?php if (strtolower($row['status']) === 'approved'): ?>
                                        <span class="badge bg-success"><?php echo ucfirst($row['status']); ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-danger"><?php echo ucfirst($row['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this reservation?');">
                                            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_reservation" class="btn btn-danger btn-sm">
                                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">delete</span>
                                                Delete
                                            </button>
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

    <script src="../resident-side/javascript/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

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
        
        // Position the dropdown below the button
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

    // Prevent dropdown from closing when clicking inside
    filterMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Apply filters
    applyButton.addEventListener('click', function() {
        let selectedFacilities = [];
        document.querySelectorAll('.facility-checkbox:checked').forEach(cb => {
            selectedFacilities.push(cb.value);
        });

        let selectedStatuses = [];
        document.querySelectorAll('.status-checkbox:checked').forEach(cb => {
            selectedStatuses.push(cb.value);
        });

        // Filter table rows
        document.querySelectorAll("#reservationTable tbody tr").forEach(row => {
            let facility = row.children[1].textContent.trim();
            let status = row.children[6].textContent.trim().toLowerCase();

            let facilityMatch = selectedFacilities.length === 0 || selectedFacilities.includes(facility);
            let statusMatch = selectedStatuses.length === 0 || selectedStatuses.includes(status);

            row.style.display = (facilityMatch && statusMatch) ? '' : 'none';
        });

        filterMenu.classList.remove('show');
    });

    // Clear all filters
    clearButton.addEventListener('click', function() {
        document.querySelectorAll('.facility-checkbox, .status-checkbox').forEach(cb => {
            cb.checked = false;
        });

        // Show all rows
        document.querySelectorAll("#reservationTable tbody tr").forEach(row => {
            row.style.display = '';
        });

        filterMenu.classList.remove('show');
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>