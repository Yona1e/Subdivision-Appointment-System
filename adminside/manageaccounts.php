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

// Database configuration
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Profile picture fallback
$profilePic = (!empty($loggedInUser['ProfilePictureURL']) && file_exists('../' . $loggedInUser['ProfilePictureURL']))
    ? '../' . $loggedInUser['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($loggedInUser['FirstName'] . ' ' . $loggedInUser['LastName']);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $userIdToUpdate = $_POST['user_id'];
    $newStatus = $_POST['status'];
    $newEmail = trim($_POST['email']);
    $newPassword = trim($_POST['password'] ?? '');

    // Validations
    if (!empty($newPassword) && strlen($newPassword) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("Location: manageaccounts.php");
        exit();
    }

    // Update status and email
    $updateStmt = $conn->prepare("UPDATE users SET Status = ?, Email = ? WHERE user_id = ?");
    $updateStmt->execute([$newStatus, $newEmail, $userIdToUpdate]);

    // Update password only if provided
    if (!empty($newPassword)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $passStmt = $conn->prepare("UPDATE users SET Password = ? WHERE user_id = ?");
        $passStmt->execute([$hashedPassword, $userIdToUpdate]);
    }

    $_SESSION['success'] = "Account updated successfully.";
    header("Location: manageaccounts.php");
    exit();
}

// Fetch Accounts - Order: Active first, then Archived; within each, Admin first then Resident; newest first
$query = "SELECT user_id, FirstName, LastName, Email, Role, Status, ProfilePictureURL, Birthday, Block, Lot, StreetName 
          FROM users 
          ORDER BY 
              CASE Status WHEN 'Active' THEN 0 WHEN 'Archived' THEN 1 ELSE 2 END,
              CASE Role WHEN 'Admin' THEN 0 WHEN 'Resident' THEN 1 ELSE 2 END,
              user_id DESC";

$stmt = $conn->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


$statuses = array_unique(array_column($users, 'Status'));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="adminside.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link rel="stylesheet" href="reservations-filter.css">
    <style>
        .profile-img-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .user-row {
            cursor: pointer;
        }

        /* Hide ID column in accounts table */
        #accountsTable th:first-child,
        #accountsTable td:first-child {
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
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-photo">
                    <div class="profile-info">
                        <p class="profile-name">
                            <?= $userName ?>
                        </p>
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
                        <a href="reservations.php" class="menu-link">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="quick-reservation/quick-reservation.php" class="menu-link">
                            <img src="../asset/Vector.png" class="menu-icon">
                            <span class="menu-label">Quick Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="manageaccounts.php" class="menu-link active">
                            <img src="../asset/manage2.png" alt="Manage Accounts Icon" class="menu-icon">
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

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="reservation-card">
                <div class="page-header">
                    Manage Accounts
                </div>
                <div class="card-body">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']);
                            unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']);
                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- SEARCH + FILTER ROW -->
                    <div class="search-filter-row">
                        <input type="text" id="searchInput" class="form-control search-bar"
                            placeholder="Search by name or email...">

                        <div class="filter-dropdown">
                            <button id="filterButton" class="btn btn-primary">
                                <span class="material-symbols-outlined"
                                    style="font-size: 18px; vertical-align: middle;">filter_list</span>
                                Filter
                            </button>

                            <div id="filterMenu" class="filter-menu">
                                <div class="filter-section">
                                    <h6>Status</h6>
                                    <div class="filter-option">
                                        <input type="checkbox" class="status-checkbox" value="Active"
                                            id="status-Active">
                                        <label for="status-Active">Active</label>
                                    </div>
                                    <div class="filter-option">
                                        <input type="checkbox" class="status-checkbox" value="Archived"
                                            id="status-Archived">
                                        <label for="status-Archived">Archived</label>
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
                        <table class="table table-bordered table-hover" id="accountsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $userPic = !empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL'])
                                        ? '../' . $user['ProfilePictureURL']
                                        : '../asset/default-profile.png';

                                    $statusClass = match ($user['Status']) {
                                        'Active' => 'bg-success',
                                        'Archived' => 'bg-secondary',
                                        default => 'bg-warning text-dark'
                                    };
                                    ?>
                                    <tr class="user-row" data-id="<?= $user['user_id'] ?>"
                                        data-firstname="<?= htmlspecialchars($user['FirstName']) ?>"
                                        data-lastname="<?= htmlspecialchars($user['LastName']) ?>"
                                        data-email="<?= htmlspecialchars($user['Email']) ?>"
                                        data-role="<?= htmlspecialchars($user['Role']) ?>"
                                        data-status="<?= htmlspecialchars($user['Status']) ?>"
                                        data-pic="<?= htmlspecialchars($userPic) ?>"
                                        data-birthday="<?= htmlspecialchars($user['Birthday'] ?? 'N/A') ?>"
                                        data-address="<?= htmlspecialchars(($user['Block'] ?? '') . ' ' . ($user['Lot'] ?? '') . ' ' . ($user['StreetName'] ?? '')) ?>">
                                        <td>
                                            <?= $user['user_id'] ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $userPic ?>" alt="Prof" class="profile-img-small">
                                                <span>
                                                    <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($user['Email']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($user['Role']) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($user['Status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center" id="paginationControls">
                            <!-- JS injected -->
                        </ul>
                    </nav>

                </div>
            </div>
        </div>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" onsubmit="return validateForm()">
                    <div class="modal-header">
                        <h5 class="modal-title">User Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- ... (Rest of modal body remains unchanged, this is just to attach the listener to the form tag) ... -->
                        <div class="row">
                            <!-- Left Column: Profile Picture & Basic Info -->
                            <div class="col-md-4 text-center border-end">
                                <img id="mPic" src="" alt="Profile" class="rounded-circle mb-3"
                                    style="width: 120px; height: 120px; object-fit: cover;">
                                <h5 id="mName" class="fw-bold mb-1"></h5>
                                <p class="mb-2"><span class="badge bg-info text-dark" id="mRole"></span></p>
                                <p class="text-muted small mb-0">User ID: <span id="mIdDisplay"></span></p>
                            </div>

                            <!-- Right Column: Details & Editable Fields -->
                            <div class="col-md-8">
                                <div class="row g-3">
                                    <!-- Read-only Details -->
                                    <div class="col-12">
                                        <h6 class="text-muted mb-2">Personal Information</h6>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">First Name</label>
                                        <p class="fw-semibold mb-2" id="mFirstName"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Last Name</label>
                                        <p class="fw-semibold mb-2" id="mLastName"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Birthday</label>
                                        <p class="fw-semibold mb-2" id="mBirthday"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Address</label>
                                        <p class="fw-semibold mb-2" id="mAddress"></p>
                                    </div>

                                    <div class="col-12">
                                        <hr class="my-2">
                                    </div>

                                    <!-- Editable Fields -->
                                    <div class="col-12">
                                        <h6 class="text-muted mb-2">Account Settings</h6>
                                    </div>
                                    <div class="col-12">
                                        <label for="emailInput" class="form-label small text-muted">Email
                                            Address</label>
                                        <input type="email" class="form-control" id="emailInput" name="email" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="passwordInput" class="form-label small text-muted">New Password
                                            <span class="text-muted">(leave blank to keep current)</span></label>
                                        <input type="password" class="form-control" id="passwordInput" name="password"
                                            placeholder="Enter new password...">
                                    </div>
                                    <div class="col-12">
                                        <label for="statusSelect" class="form-label small text-muted">Status</label>
                                        <select name="status" id="statusSelect" class="form-select">
                                            <option value="Active">Active</option>
                                            <option value="Archived">Archived</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="user_id" id="mUserId">
                        <input type="hidden" name="action" value="update_user">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>

    <script>
        function validateForm() {
            const password = document.getElementById('passwordInput').value;
            if (password && password.length < 6) {
                alert('New password must be at least 6 characters long.');
                return false;
            }
            return true;
        }

        // Modal Logic
        const userModal = new bootstrap.Modal(document.getElementById('userModal'));

        document.querySelectorAll('.user-row').forEach(row => {
            row.addEventListener('click', () => {
                document.getElementById('mPic').src = row.dataset.pic;
                document.getElementById('mName').textContent = row.dataset.firstname + ' ' + row.dataset.lastname;
                document.getElementById('mFirstName').textContent = row.dataset.firstname;
                document.getElementById('mLastName').textContent = row.dataset.lastname;
                document.getElementById('mRole').textContent = row.dataset.role;
                document.getElementById('mBirthday').textContent = row.dataset.birthday;
                document.getElementById('mAddress').textContent = row.dataset.address || 'N/A';
                document.getElementById('mIdDisplay').textContent = row.dataset.id;
                document.getElementById('mUserId').value = row.dataset.id;
                document.getElementById('emailInput').value = row.dataset.email;
                document.getElementById('passwordInput').value = '';
                document.getElementById('statusSelect').value = row.dataset.status;

                userModal.show();
            });
        });

        /* ================= PAGINATION & FILTER LOGIC ================= */
        const rowsPerPage = 10;
        let currentPage = 1;
        let allRows = Array.from(document.querySelectorAll("#accountsTable tbody tr"));
        let filteredRows = [...allRows]; // Initially all rows are visible

        function displayRows(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            // Hide all rows first
            allRows.forEach(row => row.style.display = 'none');

            // Show only rows for the current page from the filtered set
            filteredRows.slice(start, end).forEach(row => row.style.display = '');

            updatePaginationControls();
        }

        function updatePaginationControls() {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            const paginationContainer = document.getElementById('paginationControls');

            let html = '';

            // Prev
            html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Previous</a>
                     </li>`;

            // Numbers
            for (let i = 1; i <= totalPages; i++) {
                if (totalPages > 7) {
                    // Simple truncation logic for many pages
                    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                                    <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                                  </li>`;
                    } else if (i === currentPage - 2 || i === currentPage + 2) {
                        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }
                } else {
                    html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                             </li>`;
                }
            }

            // Next
            html += `<li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Next</a>
                     </li>`;

            paginationContainer.innerHTML = html;
        }

        window.changePage = function (page) {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            if (page < 1 || (page > totalPages && totalPages > 0)) return;
            currentPage = page;
            displayRows(currentPage);
        }

        function applyFiltersAndSearch() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            let selectedStatuses = [];
            document.querySelectorAll('.status-checkbox:checked').forEach(cb => {
                selectedStatuses.push(cb.value);
            });

            // Filter the master list
            filteredRows = allRows.filter(row => {
                // Search Logic
                const user = row.children[1].innerText.toLowerCase(); // Use innerText to grab refined content
                const email = row.children[2].textContent.toLowerCase();
                const matchesSearch = user.includes(searchTerm) || email.includes(searchTerm);

                // Status Logic
                const status = row.querySelector('.badge').textContent.trim();
                const matchesStatus = selectedStatuses.length === 0 || selectedStatuses.includes(status);

                return matchesSearch && matchesStatus;
            });

            // Reset to page 1
            currentPage = 1;
            displayRows(currentPage);
        }

        // Event Listeners
        document.getElementById('searchInput').addEventListener('keyup', applyFiltersAndSearch);

        // Filter Dropdown Logic
        const filterButton = document.getElementById('filterButton');
        const filterMenu = document.getElementById('filterMenu');
        const applyButton = document.getElementById('applyFilters');
        const clearButton = document.getElementById('clearFilters');

        filterButton.addEventListener('click', function (e) {
            e.stopPropagation();
            filterMenu.classList.toggle('show');
            if (filterMenu.classList.contains('show')) {
                const rect = filterButton.getBoundingClientRect();
                filterMenu.style.top = (rect.bottom + 5) + 'px';
                filterMenu.style.left = (rect.right - 280) + 'px';
            }
        });

        document.addEventListener('click', function (e) {
            if (!filterMenu.contains(e.target) && e.target !== filterButton) {
                filterMenu.classList.remove('show');
            }
        });

        filterMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        applyButton.addEventListener('click', function () {
            applyFiltersAndSearch();
            filterMenu.classList.remove('show');
        });

        clearButton.addEventListener('click', function () {
            document.querySelectorAll('.status-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('searchInput').value = '';
            applyFiltersAndSearch();
            filterMenu.classList.remove('show');
        });

        // Init
        displayRows(1);

    </script>
</body>

</html>