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
} catch (PDOException $e) {
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

/* =========================================
   HANDLE AJAX ACTIONS
   ========================================= */

// Cancel Request Logic (added)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_request') {
    header('Content-Type: application/json');
    $reservation_id = $_POST['reservation_id'] ?? null;

    if ($reservation_id) {
        // Mark as overwriteable (and potentially cancelled status if needed, but per request just overwriteable=1)
        // Note: The user request said "if the resident presses a cancel button... it must be set to 1"
        // It does NOT explicitly say to change status to 'Cancelled', but usually that's implied. 
        // For now, I will ONLY set overwriteable = 1 as explicitly requested.
        $stmt = $conn->prepare("UPDATE reservations SET overwriteable = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $reservation_id, ':user_id' => $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Handle AJAX delete (hide from view)
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

// Fetch reservations with reason column
$query = "SELECT id, facility_name, event_start_date, time_start, time_end, status, reason, created_at, note, payment_proof, cost, overwriteable
          FROM reservations
          WHERE user_id = :user_id
          AND status IN ('approved','rejected','pending')
          AND resident_visible = TRUE
          AND (overwriteable = 0)
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
    <style>
        /* Modal Styling */
        .reservation-details-modal .modal-dialog {
            /* max-width removed to allow modal-lg to take effect */
        }

        .reservation-details-modal .modal-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 20px 25px;
        }

        .reservation-details-modal .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }

        .reservation-details-modal .modal-body {
            padding: 25px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            min-width: 140px;
            font-size: 0.95rem;
        }

        .detail-value {
            color: #666;
            flex: 1;
            font-size: 0.95rem;
        }

        .section-title {
            font-weight: 700;
            color: #333;
            margin-top: 20px;
            margin-bottom: 15px;
            font-size: 1rem;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .payment-proof-container {
            margin-top: 10px;
            text-align: center;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .payment-proof-img {
            max-width: 100%;
            height: auto;
            max-height: 400px;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .no-payment-proof {
            color: #999;
            font-style: italic;
            padding: 20px;
            text-align: center;
        }

        .status-badge-large {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .modal-footer {
            padding: 15px 25px;
            background-color: #f8f9fa;
        }

        .reservation-row {
            cursor: pointer;
        }
    </style>
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
                                class="menu-icon"><span class="menu-label">Home</span></a></li>
                    <li class="menu-item"><a href="../resident-side/make-reservation.php" class="menu-link"><img
                                src="../asset/makeareservation.png" class="menu-icon"><span class="menu-label">Make a
                                Reservation</span></a></li>
                    <li class="menu-item"><a href="myreservations.php" class="menu-link active"><img
                                src="../asset/reservations.png" class="menu-icon"><span class="menu-label">My
                                Reservations</span></a></li>
                    <li class="menu-item"><a href="../my-account/my-account.php" class="menu-link"><img
                                src="../asset/profile.png" class="menu-icon"><span class="menu-label">My
                                Account</span></a></li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="../adminside/log-out.php" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" class="menu-icon"><span class="menu-label">Log
                        Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="reservation-card">

                <div class="page-header">My Reservations</div>

                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        Showing <strong>pending</strong>, <strong>approved</strong>, and <strong>rejected</strong>
                        reservations. Click a row to view full details.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
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
                                    <tr class="reservation-row" data-reservation-id="<?= $reservation['id'] ?>"
                                        data-facility="<?= htmlspecialchars($reservation['facility_name']) ?>"
                                        data-date="<?= date('F d, Y', strtotime($reservation['event_start_date'])) ?>"
                                        data-time="<?= date('g:i A', strtotime($reservation['time_start'])) ?> - <?= date('g:i A', strtotime($reservation['time_end'])) ?>"
                                        data-cost="<?= number_format($reservation['cost'] ?? 0, 2) ?>"
                                        data-status="<?= ucfirst($reservation['status']) ?>"
                                        data-created="<?= date('M d, Y g:i A', strtotime($reservation['created_at'])) ?>"
                                        data-reason="<?= htmlspecialchars($reservation['reason'] ?? 'No reason provided.') ?>"
                                        data-note="<?= htmlspecialchars($reservation['note'] ?? 'No notes provided') ?>"
                                        data-payment="<?= htmlspecialchars($reservation['payment_proof'] ?? '') ?>"
                                        <?php if ($reservation['status'] === 'approved'): ?>
                                            data-invoice-url="invoice.php?id=<?= $reservation['id'] ?>"
                                        <?php endif; ?>
                                        >
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
                                            $statusClass = match ($reservation['status']) {
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger',
                                                'pending' => 'bg-warning text-white',
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
                                                    <!-- Pending status - Cancel Button -->
                                                    <button class="btn btn-sm btn-outline-danger cancel-request-btn"
                                                        data-id="<?= $reservation['id'] ?>"
                                                        title="Cancel this reservation request">
                                                        <i class="bi bi-x-circle"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Approved/Rejected status - Show buttons -->
                                                    <button class="btn btn-sm btn-danger delete-btn action-btn"
                                                        title="Remove from view">
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

    <!-- NEW DETAILS MODAL STRUCTURE -->
    <div class="modal fade reservation-details-modal" id="reservationDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reservation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reservationDetailsBody">
                    <!-- Content will be injected via JS -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentReservationId = null;

            // Row click handler
            document.querySelectorAll('.reservation-row').forEach(row => {
                row.addEventListener('click', e => {
                    // Prevent opening modal if clicking an action button/link
                    if (e.target.closest('.action-btn') || e.target.closest('a') || e.target.closest('button')) {
                        return;
                    }

                    // Get Data Attributes
                    currentReservationId = row.dataset.reservationId;
                    const facility = row.dataset.facility;
                    const date = row.dataset.date;
                    const time = row.dataset.time;
                    const cost = row.dataset.cost;
                    const status = row.dataset.status;
                    const created = row.dataset.created;
                    const reason = row.dataset.reason;
                    const note = row.dataset.note;
                    const payment = row.dataset.payment;

                    // Build Modal HTML Content
                    let statusColor = 'secondary';
                    if (status.toLowerCase() === 'approved') statusColor = 'success';
                    else if (status.toLowerCase() === 'rejected') statusColor = 'danger';
                    else if (status.toLowerCase() === 'pending') statusColor = 'warning';

                    let htmlContent = `
                            <div class="section-title">Reservation Info</div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Facility</div>
                                <div class="detail-value">${facility}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Date</div>
                                <div class="detail-value">${date}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Time Slot</div>
                                <div class="detail-value">${time}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Cost</div>
                                <div class="detail-value">&#8369;${cost}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="badge bg-${statusColor} text-${statusColor === 'warning' ? 'white' : 'white'}">
                                        ${status}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Booked On</div>
                                <div class="detail-value">${created}</div>
                            </div>
                        `;

                    // Rejection Reason (if rejected)
                    if (status.toLowerCase() === 'rejected') {
                        htmlContent += `
                                <div class="section-title text-danger">Rejection Details</div>
                                <div class="detail-row">
                                    <div class="detail-label text-danger">Reason</div>
                                    <div class="detail-value text-danger fw-bold">${reason}</div>
                                </div>
                            `;
                    }

                    // Notes
                    htmlContent += `
                            <div class="section-title">Additional Info</div>
                            <div class="detail-row">
                                <div class="detail-label">My Note</div>
                                <div class="detail-value">${note}</div>
                            </div>
                        `;

                    // Payment Proof
                    htmlContent += `<div class="section-title">Payment Proof</div>`;
                    if (payment) {
                        htmlContent += `
                                <div class="payment-proof-container">
                                    <img src="../${payment}" class="payment-proof-img" alt="Payment Proof">
                                </div>
                            `;
                    } else {
                        htmlContent += `<div class="no-payment-proof">No payment proof uploaded for this reservation.</div>`;
                    }

                    // Invoice Button (New)
                    const invoiceUrl = row.dataset.invoiceUrl;
                    if (invoiceUrl) {
                        htmlContent += `
                                <div class="mt-4 pt-3 border-top text-end">
                                    <a href="${invoiceUrl}" class="btn btn-primary" target="_blank">
                                        <i class="bi bi-file-pdf"></i> Download Invoice
                                    </a>
                                </div>
                            `;
                    }

                    // Update Modal Body
                    document.getElementById('reservationDetailsBody').innerHTML = htmlContent;

                    // Show Modal
                    new bootstrap.Modal(document.getElementById('reservationDetailsModal')).show();
                });
            });

            // Cancel Request Button Handler (New for Action Column)
            document.querySelectorAll('.cancel-request-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Stop row click
                    const reservationId = btn.getAttribute('data-id');

                    Swal.fire({
                        title: "Cancel this request?",
                        text: "This will free up the timeslot and mark it as overwriteable.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Cancel Request",
                        cancelButtonText: "No, Keep it",
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('myreservations.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=cancel_request&reservation_id=${reservationId}`
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire("Cancelled!", "Your request has been cancelled.", "success")
                                            .then(() => location.reload());
                                    } else {
                                        Swal.fire("Error", "Could not cancel request.", "error");
                                    }
                                });
                        }
                    });
                });
            });

            // Delete button handler
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Stop row click
                    const row = btn.closest('tr');
                    const id = row.dataset.reservationId;

                    Swal.fire({
                        title: "Remove this reservation?",
                        text: "This will clean up your list but keep the record in our system.",
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