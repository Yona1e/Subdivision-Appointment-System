<?php
session_start();

/* Prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

/* DB */
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Admin info */
$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL']))
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);

/* Hide reservation */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("UPDATE reservations SET admin_visible = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

/* Reservations */
$res_sql = "
SELECT
    r.id,
    r.user_id,
    r.facility_name,
    r.phone,
    r.event_start_date,
    r.time_start,
    r.time_end,
    r.status,
    r.note,
    r.updated_at,
    r.payment_proof,
    r.cost,
    u.FirstName,
    u.LastName,
    u.Role
FROM reservations r
LEFT JOIN users u ON r.user_id = u.user_id
WHERE r.status IN ('approved','rejected')
AND r.admin_visible = 1
AND r.overwriteable = 0
ORDER BY r.updated_at DESC, r.id DESC";

$reservations = $conn->query($res_sql);

/* Facilities */
$facility_sql = "SELECT DISTINCT facility_name FROM reservations WHERE admin_visible = 1 AND status IN ('approved', 'rejected') AND overwriteable = 0 ORDER BY facility_name ASC";
$facility_list = $conn->query($facility_sql);
$facilities_array = [];
while ($f = $facility_list->fetch_assoc()) {
    $facilities_array[] = $f['facility_name'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Approved & Rejected Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="adminside.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link rel="stylesheet" href="reservations-filter.css">
    <style>
        /* Modal Styling (Synced with myreservations.php) */
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }

        .modal-title {
            font-weight: 600;
            color: #333;
        }

        .modal-body {
            padding: 25px;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
            margin-top: 20px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 12px;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            width: 140px;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }

        .detail-value {
            flex: 1;
            color: #222;
            font-size: 0.9rem;
            word-break: break-word;
        }

        .payment-proof-img {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background: #f8f9fa;
        }

        /* Hide ID column matches reserverequests */
        table thead th:first-child,
        table tbody td:first-child {
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
                        <a href="reservations.php" class="menu-link  active">
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
                        <a href="manageaccounts.php" class="menu-link">
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


        <!-- MAIN -->
        <div class="main-content">
            <div class="reservation-card">
                <div class="page-header">Approved & Rejected Reservations</div>
                <div class="card-body">

                    <div class="alert alert-info">
                        Approved and rejected reservations. Click a row to view full details.
                    </div>

                    <!-- SEARCH + FILTER ROW -->
                    <div class="search-filter-row mb-3">
                        <input type="text" id="searchInput" class="form-control search-bar"
                            placeholder="Search by details...">

                        <div class="filter-dropdown" style="position: relative;">
                            <button id="filterButton" class="btn btn-primary">
                                <span class="material-symbols-outlined"
                                    style="font-size: 18px; vertical-align: middle;">filter_list</span>
                                Filter
                            </button>

                            <div id="filterMenu" class="filter-menu">
                                <div class="filter-section">
                                    <h6>Facility</h6>
                                    <?php foreach ($facilities_array as $fac): ?>
                                        <div class="filter-option">
                                            <input type="checkbox" class="facility-checkbox"
                                                value="<?= htmlspecialchars($fac) ?>"
                                                id="fac-<?= htmlspecialchars(str_replace(' ', '', $fac)) ?>">
                                            <label
                                                for="fac-<?= htmlspecialchars(str_replace(' ', '', $fac)) ?>"><?= htmlspecialchars($fac) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <hr class="my-2">
                                <div class="filter-section">
                                    <h6>Status</h6>
                                    <div class="filter-option">
                                        <input type="checkbox" class="status-checkbox" value="Approved"
                                            id="status-Approved">
                                        <label for="status-Approved">Approved</label>
                                    </div>
                                    <div class="filter-option">
                                        <input type="checkbox" class="status-checkbox" value="Rejected"
                                            id="status-Rejected">
                                        <label for="status-Rejected">Rejected</label>
                                    </div>
                                </div>

                                <div class="filter-actions mt-3">
                                    <button class="btn btn-secondary btn-sm" id="clearFilters">Clear All</button>
                                    <button class="btn btn-primary btn-sm" id="applyFilters">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- TABLE -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th class="id-column">ID</th>
                                    <th>Facility</th>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th>Event Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php while ($row = $reservations->fetch_assoc()): ?>
                                    <tr class="reservation-row"
                                        data-facility="<?= htmlspecialchars($row['facility_name']) ?>"
                                        data-user="<?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?>"
                                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                        data-date="<?= date('M d, Y', strtotime($row['event_start_date'])) ?>"
                                        data-time="<?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?>"
                                        data-cost="<?= number_format($row['cost'] ?? 0, 2) ?>"
                                        data-status="<?= ucfirst($row['status']) ?>"
                                        data-note="<?= htmlspecialchars($row['note'] ?: 'No notes provided') ?>"
                                        data-updated="<?= date('M d, Y g:i A', strtotime($row['updated_at'])) ?>"
                                        data-payment="<?= htmlspecialchars($row['payment_proof']) ?>" <?php
                                          // Determine Invoice Link & Visibility
                                          $isResident = !empty($row['user_id']) && isset($row['Role']) && $row['Role'] === 'Resident';
                                          $showPdf = $isResident || (!empty($row['phone']));
                                          if ($showPdf && $row['status'] !== 'rejected'):
                                              $invoiceLink = $isResident ? 'invoice-resident.php?id=' . $row['id'] : 'invoice.php?id=' . $row['id'];
                                              ?>
                                            data-invoice-url="<?= $invoiceLink ?>" <?php endif; ?>>

                                        <td class="id-column">
                                            <?= $row['id'] ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['facility_name']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['phone']) ?>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($row['event_start_date'])) ?>
                                        </td>

                                        <td>
                                            <?= date('g:i A', strtotime($row['time_start'])) ?> -
                                            <?= date('g:i A', strtotime($row['time_end'])) ?>
                                        </td>
                                        <td>
                                            <span
                                                class="badge <?= $row['status'] == 'approved' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                                <button name="delete_reservation" class="btn btn-sm btn-danger delete-btn">
                                                    <i class="bi bi-trash"></i> Delete
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

    <!-- MODAL -->
    <div class="modal fade" id="reservationModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reservation Details</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Content injected via JS -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.reservation-row').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('form') || e.target.closest('a')) return;

                const facility = row.dataset.facility;
                const user = row.dataset.user;
                const phone = row.dataset.phone;
                const date = row.dataset.date;
                const time = row.dataset.time;
                const cost = row.dataset.cost;
                const status = row.dataset.status;
                const updated = row.dataset.updated;
                const note = row.dataset.note;
                const payment = row.dataset.payment;
                const invoiceUrl = row.dataset.invoiceUrl;

                let statusColor = status === 'Approved' ? 'success' : 'danger';

                const modalBody = document.querySelector('#reservationModal .modal-body');

                let htmlContent = `
                    <div class="section-title">Reservation Info</div>
                    <div class="detail-row">
                        <div class="detail-label">Facility</div>
                        <div class="detail-value">${facility}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">User</div>
                        <div class="detail-value">${user}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value">${phone}</div>
                    </div>
                     <div class="detail-row">
                        <div class="detail-label">Date</div>
                        <div class="detail-value">${date}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Time</div>
                        <div class="detail-value">${time}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Cost</div>
                        <div class="detail-value">&#8369;${cost}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="badge bg-${statusColor}">${status}</span>
                        </div>
                    </div>
                     <div class="detail-row">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value">${updated}</div>
                    </div>

                    <div class="section-title">Additional Info</div>
                    <div class="detail-row">
                        <div class="detail-label">Resident Note</div>
                        <div class="detail-value">${note}</div>
                    </div>

                    <div class="section-title">Payment Proof</div>
                    <div class="text-center mt-2">
                        ${payment
                        ? `<img src="../${payment}" class="payment-proof-img">`
                        : '<div class="text-muted fst-italic py-3">No payment proof uploaded</div>'}
                    </div>
                `;

                if (invoiceUrl) {
                    htmlContent += `
                        <div class="mt-4 pt-3 border-top text-end">
                            <a href="${invoiceUrl}" class="btn btn-primary" target="_blank">
                                <i class="bi bi-file-pdf"></i> Download Invoice
                            </a>
                        </div>
                    `;
                }

                modalBody.innerHTML = htmlContent;

                new bootstrap.Modal(document.getElementById('reservationModal')).show();
            });
        });

        /* FILTER & SEARCH LOGIC */
        // Search
        document.getElementById('searchInput').addEventListener('keyup', function () {
            let search = this.value.toLowerCase();
            document.querySelectorAll(".reservation-row").forEach(row => {
                let facility = row.dataset.facility.toLowerCase();
                let user = row.dataset.user.toLowerCase();
                let phone = row.dataset.phone.toLowerCase();
                let date = row.dataset.date.toLowerCase();
                let time = row.dataset.time.toLowerCase();
                let status = row.dataset.status.toLowerCase();

                row.style.display = (
                    facility.includes(search) ||
                    user.includes(search) ||
                    phone.includes(search) ||
                    date.includes(search) ||
                    time.includes(search) ||
                    status.includes(search)
                ) ? '' : 'none';
            });
        });

        // Filter Dropdown
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
            let selectedFacilities = [];
            document.querySelectorAll('.facility-checkbox:checked').forEach(cb => selectedFacilities.push(cb.value));

            let selectedStatuses = [];
            document.querySelectorAll('.status-checkbox:checked').forEach(cb => selectedStatuses.push(cb.value));

            document.querySelectorAll(".reservation-row").forEach(row => {
                let facility = row.dataset.facility;
                let status = row.querySelector('.badge').textContent.trim();

                let facilityMatch = selectedFacilities.length === 0 || selectedFacilities.includes(facility);
                let statusMatch = selectedStatuses.length === 0 || selectedStatuses.includes(status);

                row.style.display = (facilityMatch && statusMatch) ? '' : 'none';
            });
            filterMenu.classList.remove('show');
        });

        clearButton.addEventListener('click', function () {
            document.querySelectorAll('.facility-checkbox, .status-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll(".reservation-row").forEach(row => row.style.display = '');
            filterMenu.classList.remove('show');
        });
    </script>
    <script src="../resident-side/javascript/sidebar.js"></script>
</body>

</html>
<?php $conn->close(); ?>