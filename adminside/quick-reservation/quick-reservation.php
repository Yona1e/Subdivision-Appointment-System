<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

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

// Handle AJAX request for fetching bookings by facility
if (isset($_GET['action']) && $_GET['action'] === 'fetch_bookings') {
    header('Content-Type: application/json');
    
    $facility = isset($_GET['facility']) ? trim($_GET['facility']) : null;
    
    if (!$facility || empty($facility)) {
        echo json_encode(['status' => false, 'msg' => 'No facility selected', 'data' => []]);
        exit();
    }
    
    try {
        // Fetch reservations from ALL users (both regular users and admins)
        $sql = "SELECT 
                    r.*,
                    CONCAT(u.FirstName, ' ', u.LastName) as title,
                    u.Email as resident_email,
                    u.Role as user_role
                FROM reservations r
                INNER JOIN users u ON r.user_id = u.user_id
                WHERE r.facility_name = :facility
                AND r.status IN ('confirmed', 'approved', 'pending', 'rejected')
                ORDER BY r.event_start_date DESC, r.time_start DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':facility', $facility, PDO::PARAM_STR);
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            // Handle different possible ID column names
            $reservationId = null;
            if (isset($booking['id'])) {
                $reservationId = $booking['id'];
            } elseif (isset($booking['reservation_id'])) {
                $reservationId = $booking['reservation_id'];
            } elseif (isset($booking['reservationID'])) {
                $reservationId = $booking['reservationID'];
            }
            
            $formattedBookings[] = [
                'id' => $reservationId,
                'title' => $booking['title'],
                'start' => $booking['event_start_date'] . 'T' . $booking['time_start'],
                'end' => $booking['event_end_date'] . 'T' . $booking['time_end'],
                'status' => $booking['status'],
                'phone' => isset($booking['phone']) ? $booking['phone'] : '',
                'note' => isset($booking['note']) ? $booking['note'] : '',
                'email' => $booking['resident_email'],
                'user_role' => isset($booking['user_role']) ? $booking['user_role'] : 'Unknown'
            ];
        }
        
        echo json_encode([
            'status' => true,
            'data' => $formattedBookings,
            'count' => count($formattedBookings)
        ]);
        exit();
        
    } catch(PDOException $e) {
        echo json_encode([
            'status' => false,
            'msg' => 'Error fetching bookings: ' . $e->getMessage(),
            'data' => []
        ]);
        exit();
    }
}

// Handle AJAX request for creating admin reservation (directly approved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_reservation') {
    header('Content-Type: application/json');
    
    // Get and validate input
    $facility = isset($_POST['facility']) ? trim($_POST['facility']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $timeStart = isset($_POST['time_start']) ? trim($_POST['time_start']) : '';
    $timeEnd = isset($_POST['time_end']) ? trim($_POST['time_end']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    // Validation
    if (empty($facility) || empty($date) || empty($timeStart) || empty($timeEnd) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }

    // Validate phone number format
    if (!preg_match('/^09\d{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        exit();
    }

    // Validate date is not in the past
    $selectedDate = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    if ($selectedDate < $today) {
        echo json_encode(['success' => false, 'message' => 'Cannot book past dates']);
        exit();
    }

    // Check for conflicts
    try {
        $checkSql = "SELECT COUNT(*) as count 
                     FROM reservations 
                     WHERE facility_name = :facility 
                     AND event_start_date = :date 
                     AND status IN ('confirmed', 'approved', 'pending')
                     AND (
                         (time_start < :time_end AND time_end > :time_start)
                     )";
        
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':facility', $facility);
        $checkStmt->bindParam(':date', $date);
        $checkStmt->bindParam(':time_start', $timeStart);
        $checkStmt->bindParam(':time_end', $timeEnd);
        $checkStmt->execute();
        
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
            exit();
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error checking availability']);
        exit();
    }

    // Get admin user_id from session
    $user_id = $_SESSION['user_id'];

    // Insert reservation with 'approved' status (admin bypass - no payment required)
    try {
        $insertSql = "INSERT INTO reservations 
                      (user_id, facility_name, event_start_date, event_end_date, time_start, time_end, phone, note, status, created_at) 
                      VALUES 
                      (:user_id, :facility, :start_date, :end_date, :time_start, :time_end, :phone, :note, 'approved', NOW())";
        
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bindParam(':user_id', $user_id);
        $insertStmt->bindParam(':facility', $facility);
        $insertStmt->bindParam(':start_date', $date);
        $insertStmt->bindParam(':end_date', $date);
        $insertStmt->bindParam(':time_start', $timeStart);
        $insertStmt->bindParam(':time_end', $timeEnd);
        $insertStmt->bindParam(':phone', $phone);
        $insertStmt->bindParam(':note', $note);
        
        $insertStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Reservation created and approved successfully',
            'reservation_id' => $conn->lastInsertId()
        ]);
        exit();
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating reservation: ' . $e->getMessage()]);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login/login.php");
    exit();
}

$profilePic = !empty($user['ProfilePictureURL'])
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

$userName = htmlspecialchars(trim($user['FirstName'] . ' ' . $user['LastName']));
$loggedInUserName = $userName;
$loggedInUserProfilePic = $profilePic;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Reservation - Admin Dashboard</title>
    
    <link rel="stylesheet" href="../../resident-side/make-reservation1.css">
    <link rel="stylesheet" href="../../resident-side/style/side-navigation1.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- jQuery (MUST be loaded first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    
    <!-- FullCalendar v3 CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    
    <style>
    /* Responsive Calendar Styles */
    .container.py-5 {
        padding-top: 30px !important;
        padding-bottom: 30px !important;
        max-width: 100%;
    }

    /* ===== Make container a bit smaller (scoped) ===== */
.reservation-card > .container {
    max-width: 1000px;   /* adjust: 900px / 950px / 1000px */
    padding-left: 12px;
    padding-right: 12px;
}

@media (max-width: 992px) {
    .reservation-card > .container {
        max-width: 100%;
        padding-left: 10px;
        padding-right: 10px;|
    }
}

    .calendar-wrapper {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        overflow: hidden;
    }

    #calendar {
        max-width: 100%;
        font-size: 14px;
    }

    .fc-toolbar {
        margin-bottom: 20px;
    }

    .fc-toolbar h2 {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .fc-day {
        min-height: 100px;
    }

    /* Tablets (1024px and below) */
    @media (max-width: 1024px) {
        .calendar-wrapper {
            padding: 15px;
        }
        
        .fc-toolbar h2 {
            font-size: 1.3rem;
        }
        
        .fc-day {
            min-height: 85px;
        }
    }

    /* Tablets (768px and below) */
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }
        
        .reservation-card {
            padding: 20px 15px;
        }
        
        .page-header {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .container.py-5 {
            padding-top: 20px !important;
            padding-bottom: 20px !important;
        }
        
        .calendar-wrapper {
            padding: 12px;
        }
        
        .fc-toolbar {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .fc-toolbar .fc-left,
        .fc-toolbar .fc-center,
        .fc-toolbar .fc-right {
            width: 100%;
            text-align: center;
        }
        
        .fc-toolbar h2 {
            font-size: 1.2rem;
        }
        
        .fc-day {
            min-height: 70px;
        }
        
        .fc-day-header {
            font-size: 0.85rem;
            padding: 8px 3px;
        }
        
        .fc-event {
            font-size: 0.75rem;
        }
        
        .fc-button {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        
        #facility_select {
            max-width: 100%;
        }
    }

    /* Mobile (576px and below) */
    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
        
        .reservation-card {
            padding: 15px 10px;
        }
        
        .page-header {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .container.py-5 {
            padding-top: 15px !important;
            padding-bottom: 15px !important;
        }
        
        .calendar-wrapper {
            padding: 10px;
        }
        
        .fc-toolbar h2 {
            font-size: 1.1rem;
        }
        
        .fc-day {
            min-height: 60px;
        }
        
        .fc-day-header {
            font-size: 0.75rem;
            padding: 6px 2px;
        }
        
        .fc-day-number {
            font-size: 0.8rem;
            padding: 4px;
        }
        
        .fc-event {
            font-size: 0.7rem;
            padding: 1px 3px;
        }
        
        .fc-button {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        
        .fc-agendaWeek-button,
        .fc-agendaDay-button {
            display: none !important;
        }
        
        #facility_select {
            font-size: 0.9rem;
            padding: 8px 12px;
        }
        
        .modal-dialog {
            margin: 10px;
            max-width: calc(100% - 20px);
        }
        
        .modal-body {
            padding: 12px;
        }
        
        .slots-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 6px;
        }
        
        .slot-btn {
            font-size: 0.8rem;
            padding: 8px 6px;
        }
    }

    /* Very Small Mobile (400px and below) */
    @media (max-width: 400px) {
        .page-header {
            font-size: 1.1rem;
        }
        
        .calendar-wrapper {
            padding: 8px;
        }
        
        .fc-toolbar h2 {
            font-size: 1rem;
        }
        
        .fc-day {
            min-height: 50px;
        }
        
        .fc-day-header {
            font-size: 0.7rem;
            padding: 5px 1px;
        }
        
        .fc-day-number {
            font-size: 0.75rem;
            padding: 3px;
        }
        
        .fc-event {
            font-size: 0.65rem;
        }
    }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <div class="profile-section">
                    <img src="<?= htmlspecialchars($loggedInUserProfilePic) ?>" alt="Profile" class="profile-photo"
                        onerror="this.src='../asset/profile.jpg'">
                    <div class="profile-info">
                        <p class="profile-name"><?= htmlspecialchars($loggedInUserName) ?></p>
                        <p class="profile-role">Admin</p>
                    </div>
                </div>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <!-- Menu List -->
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="../overview.php" class="menu-link">
                            <img src="../../asset/home.png" alt="Home Icon" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../reserverequests.php" class="menu-link">
                            <img src="../../asset/makeareservation.png" alt="Requests Icon" class="menu-icon">
                            <span class="menu-label">Requests</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../reservations.php" class="menu-link">
                            <img src="../../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="quick-reservation.php" class="menu-link active">
                            <img src="../../asset/Vector.png" alt="Quick Reservation Icon" class="menu-icon">
                            <span class="menu-label">Quick Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../create-account.php" class="menu-link">
                            <img src="../../asset/profile.png" alt="Create Account Icon" class="menu-icon">
                            <span class="menu-label">Create Account</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="logout-section">
                <a href="../../adminside/log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- RESERVATION CARD -->
            <div class="reservation-card">
                <div class="page-header">
                    Quick Reservation
                </div>
                
                <!-- FACILITY SELECTION -->
                <div class="container">
                    <!-- Facility Dropdown -->
                    <div class="form-group mb-4">
                        <label for="facility_select" class="form-label"><strong>Select Facility:</strong></label>
                        <select class="form-select form-control" id="facility_select" style="max-width: 400px;">
                            <option value="" selected disabled>Choose a facility...</option>
                            <option value="Chapel">Chapel</option>
                            <option value="Basketball Court">Basketball Court</option>
                            <option value="Multipurpose Hall">Multipurpose Hall</option>
                            <option value="Tennis Court">Tennis Court</option>
                        </select>
                    </div>
                    
                    <!-- CALENDAR SECTION -->
                    <div class="container py-5" style="display: block;padding-top: 30px !important;">
                        <div class="row g-4">
                            <!-- Calendar (Full Width) -->
                            <div class="col-lg-12">
                                <div class="calendar-wrapper">
                                    <div id="calendar"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">Book a Facility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Hidden Date Fields -->
                    <input type="hidden" id="event_start_date">
                    <input type="hidden" id="event_end_date">
                    <input type="hidden" id="selected_time_start">
                    <input type="hidden" id="selected_time_end">
                    <input type="hidden" id="selected_facility">

                    <!-- Selected Date Display -->
                    <div class="alert alert-info" role="alert">
                        <strong>Selected Date:</strong> <span id="display_selected_date"></span>
                    </div>

                    <!-- Facility Name (Read-only display) -->
                    <div class="form-group mb-3">
                        <label for="facility_name">Facility Name: <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="facility_name" readonly
                            style="background-color: #e9ecef; cursor: not-allowed;">
                        <small class="form-text text-muted">
                            Please select a facility from the cards above before choosing a date.
                        </small>
                    </div>

                    <!-- Phone Number -->
                    <div class="form-group mb-3">
                        <label for="phone">Phone Number: <span class="text-danger">*</span></label>
                        <input type="text" 
                            class="form-control" 
                            id="phone" 
                            name="phone"
                            placeholder="09123456789"
                            maxlength="11"
                            pattern="^09\d{9}$"
                            required>
                        <div class="invalid-feedback" id="phoneFeedback">
                            Please enter a valid Philippine mobile number (e.g., 09123456789)
                        </div>
                    </div>

                    <!-- Time Slots -->
                    <div class="form-group mb-3">
                        <label>Select Time Slot: <span class="text-danger">*</span></label>
                        <div class="card time-slot-card">
                            <div class="card-header fw-bold">
                                Available Time Slots
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    ⓘ Each slot: 1 hour | Click to select
                                </p>

                                <div class="slots-container">
                                    <button type="button" class="btn slot-btn" data-start="08:00"
                                        data-end="09:00">
                                        8:00 AM - 9:00 AM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="09:00"
                                        data-end="10:00">
                                        9:00 AM - 10:00 AM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="10:00"
                                        data-end="11:00">
                                        10:00 AM - 11:00 AM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="11:00"
                                        data-end="12:00">
                                        11:00 AM - 12:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="12:00"
                                        data-end="13:00">
                                        12:00 PM - 1:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="13:00"
                                        data-end="14:00">
                                        1:00 PM - 2:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="14:00"
                                        data-end="15:00">
                                        2:00 PM - 3:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="15:00"
                                        data-end="16:00">
                                        3:00 PM - 4:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="16:00"
                                        data-end="17:00">
                                        4:00 PM - 5:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="17:00"
                                        data-end="18:00">
                                        5:00 PM - 6:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="18:00"
                                        data-end="19:00">
                                        6:00 PM - 7:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="19:00"
                                        data-end="20:00">
                                        7:00 PM - 8:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="20:00"
                                        data-end="21:00">
                                        8:00 PM - 9:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="21:00"
                                        data-end="22:00">
                                        9:00 PM - 10:00 PM
                                    </button>
                                    <button type="button" class="btn slot-btn" data-start="22:00"
                                        data-end="23:00">
                                        10:00 PM - 11:00 PM
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NOTE -->
                    <div class="form-group mb-3">
                        <label for="event_note">Additional Notes (Optional)</label>
                        <textarea class="form-control"
                            placeholder="Leave a note here (e.g., purpose of reservation, special requests)"
                            id="event_note" rows="3"></textarea>
                    </div>
                </div>

                <!-- Button -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveReservationBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status"
                            aria-hidden="true"></span>
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS (with Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Other JS -->
    <script src="../../resident-side/javascript/sidebar.js"></script>
    <script src="quick-reservation.js"></script>
</body>
</html>