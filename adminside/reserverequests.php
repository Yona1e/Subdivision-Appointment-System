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
$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL']))
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);

$message = "";

/* ========= POST HANDLING ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Helper function to fetch reservation details for logging
    function getReservationDetails($conn, $id)
    {
        $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    // Helper to log action
    function logAction($conn, $adminId, $userId, $actionType, $entityId, $details, $remarks = '')
    {
        $detailJson = json_encode($details);
        $stmt = $conn->prepare("INSERT INTO auditlogs (AdminID, UserID, ActionType, EntityType, EntityID, EntityDetails, Remarks, Timestamp) VALUES (?, ?, ?, 'Reservation', ?, ?, ?, NOW())");
        $stmt->bind_param("iisiss", $adminId, $userId, $actionType, $entityId, $detailJson, $remarks);
        $stmt->execute();
    }

    if (isset($_POST['approve_reservation'])) {
        $reservation_id = intval($_POST['reservation_id']);

        // Fetch details BEFORE update for the log
        $reservationDetails = getReservationDetails($conn, $reservation_id);

        if ($reservationDetails) {
            $stmt = $conn->prepare("UPDATE reservations SET status = 'approved', reason = NULL WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);

            if ($stmt->execute()) {
                // Log the approval
                logAction($conn, $_SESSION['user_id'], $reservationDetails['user_id'], 'Approved', $reservation_id, $reservationDetails, 'Reservation approved by admin');
                $message = "Reservation #$reservation_id has been approved.";
            }
            $stmt->close();
        }
    }

    if (isset($_POST['confirm_reject'])) {
        $reservation_id = intval($_POST['reservation_id']);
        $reason = trim($_POST['reason']);

        // Fetch details BEFORE update
        $reservationDetails = getReservationDetails($conn, $reservation_id);

        if ($reservationDetails) {
            $stmt = $conn->prepare("UPDATE reservations SET status = 'rejected', reason = ? WHERE id = ?");
            $stmt->bind_param("si", $reason, $reservation_id);

            if ($stmt->execute()) {
                // Log the rejection
                logAction($conn, $_SESSION['user_id'], $reservationDetails['user_id'], 'Rejected', $reservation_id, $reservationDetails, "Reason: $reason");
                $message = "Reservation #$reservation_id has been rejected.";
            }
            $stmt->close();
        }
    }
}

$notes_column_exists = false;
$result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'notes'");
if ($result && $result->num_rows > 0) {
    $notes_column_exists = true;
}

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
                r.cost,
                " . ($notes_column_exists ? "r.notes," : "") . "
                u.FirstName,
                u.LastName
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE LOWER(r.status) = 'pending'
            AND r.overwriteable = 0
            ORDER BY r.id DESC";

$reservations = $conn->query($res_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Pending Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="adminside.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
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

        /* Hide ID column on this page */
        table thead th:first-child,
        table tbody td:first-child {
            display: none;
        }
    </style>
</head>

<body>

    <div class="app-layout">

        <!-- ================== SIDEBAR (FULL, RESTORED) ================== -->
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
                            <img src="../asset/home.png" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reserverequests.php" class="menu-link active">
                            <img src="../asset/makeareservation.png" class="menu-icon">
                            <span class="menu-label">Requests</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reservations.php" class="menu-link">
                            <img src="../asset/reservations.png" class="menu-icon">
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
                            <img src="../asset/profile.png" class="menu-icon">
                            <span class="menu-label">Create Account</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="log-out.php" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>
        <!-- ============================================================= -->

        <div class="main-content">
            <div class="reservation-card">
                <div class="page-header">Pending Reservations</div>

                <div class="card-body">
                    <div class="alert alert-info">
                        All pending reservations are displayed here. Click a row to view full details.
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $message ?>
                            <button class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle">
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
                                        data-created="<?= date('M d, Y g:i A', strtotime($row['created_at'])) ?>"
                                        data-payment="<?= htmlspecialchars($row['payment_proof']) ?>">
                                        <td class="id-column">
                                            <?= $row['id'] ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['facility_name']) ?>
                                        </td>
                                        <td>
                                            <?= $row['FirstName'] . ' ' . $row['LastName'] ?>
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
                                        <td><span class="badge bg-warning text-white">Pending</span></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="approve_reservation"
                                                    class="btn btn-success btn-sm mb-1">
                                                    Approve
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger btn-sm mb-1 reject-btn"
                                                data-id="<?= $row['id'] ?>">
                                                Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

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

    <!-- ================= EXISTING DETAILS MODAL (UNCHANGED) ================= -->
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

    <!-- ================= NEW REJECT MODAL ================= -->
    <div class="modal fade" id="rejectModal">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Reservation</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="reservation_id" id="rejectReservationId">
                    <label class="form-label">Reason for rejection</label>
                    <textarea name="reason" class="form-control" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button name="confirm_reject" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.reservation-row').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('form') || e.target.classList.contains('reject-btn')) return;

                const facility = row.dataset.facility;
                const user = row.dataset.user;
                const phone = row.dataset.phone;
                const date = row.dataset.date;
                const time = row.dataset.time;
                const cost = row.dataset.cost;
                const status = row.dataset.status;
                const created = row.dataset.created;
                const note = row.dataset.note;
                const payment = row.dataset.payment;

                let statusColor = 'warning';
                if (status.toLowerCase() === 'approved') statusColor = 'success';
                else if (status.toLowerCase() === 'rejected') statusColor = 'danger';

                const modalBody = document.querySelector('#reservationModal .modal-body');
                modalBody.innerHTML = `
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
                            <span class="badge bg-${statusColor} text-${statusColor === 'warning' ? 'white' : 'white'}">${status}</span>
                        </div>
                    </div>
                     <div class="detail-row">
                        <div class="detail-label">Created On</div>
                        <div class="detail-value">${created}</div>
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

                new bootstrap.Modal(document.getElementById('reservationModal')).show();
            });
        });

        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                document.getElementById('rejectReservationId').value = btn.dataset.id;
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
            });
        });

        /* ================= PAGINATION & SEARCH LOGIC ================= */
        const rowsPerPage = 10;
        let currentPage = 1;
        let allRows = Array.from(document.querySelectorAll(".reservation-row"));
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

        function applySearch() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                const facility = row.dataset.facility.toLowerCase();
                const user = row.dataset.user.toLowerCase();
                const phone = row.dataset.phone.toLowerCase();
                return facility.includes(searchTerm) || user.includes(searchTerm) || phone.includes(searchTerm);
            });

            currentPage = 1;
            displayRows(currentPage);
        }

        document.getElementById('searchInput').addEventListener('keyup', applySearch);

        // Init
        displayRows(1);
    </script>

    <script src="../resident-side/javascript/sidebar.js"></script>
</body>

</html>

<?php $conn->close(); ?>