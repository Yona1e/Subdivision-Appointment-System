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
        // First, let's check what columns exist in your reservations table
        $sql = "SELECT 
                    r.*,
                    CONCAT(u.FirstName, ' ', u.LastName) as title,
                    u.Email as resident_email
                FROM reservations r
                INNER JOIN users u ON r.user_id = u.user_id
                WHERE r.facility_name = :facility
                AND r.status IN ('confirmed', 'approved', 'pending')
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
                'email' => $booking['resident_email']
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

// Handle AJAX request for creating quick reservation
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

    // Insert reservation
    try {
        $insertSql = "INSERT INTO reservations 
                      (user_id, facility_name, event_start_date, event_end_date, time_start, time_end, phone, note, status, created_at) 
                      VALUES 
                      (:user_id, :facility, :start_date, :end_date, :time_start, :time_end, :phone, :note, 'confirmed', NOW())";
        
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
            'message' => 'Reservation created successfully',
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
    
    <link rel="stylesheet" href="style/side-navigation1.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #f5f7fa;
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 280px;
        }

        .page-header {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }

        .facility-selector {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .facility-selector h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .facility-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .facility-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .facility-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.1);
        }

        .facility-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .facility-card img {
            width: 60px;
            height: 60px;
            margin-bottom: 0.75rem;
        }

        .facility-card h5 {
            font-size: 1rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .calendar-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .calendar-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .summary-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .summary-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .summary-info {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .summary-info h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .summary-info p {
            color: #6b7280;
            margin: 0;
        }

        .bookings-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .booking-item {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border-left: 4px solid #3b82f6;
        }

        .booking-item h6 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }

        .booking-item p {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .empty-state .material-symbols-outlined {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* FullCalendar Customization */
        #calendar {
            max-width: 100%;
        }

        .fc-toolbar {
            margin-bottom: 1.5rem !important;
        }

        .fc-toolbar-title {
            font-size: 1.25rem !important;
            font-weight: 600 !important;
        }

        .fc-button {
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
            text-transform: capitalize !important;
        }

        .fc-button:hover {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
        }

        .fc-day-grid-event {
            background-color: #3b82f6;
            border-color: #3b82f6;
            padding: 2px 4px;
            font-size: 0.85rem;
        }

        .fc-event {
            cursor: pointer;
        }

        /* Modal Styles */
        .modal-header {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-weight: 600;
        }

        .time-slot-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .slot-btn {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .slot-btn:hover:not(.disabled) {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .slot-btn.selected {
            border-color: #3b82f6;
            background: #3b82f6;
            color: white;
        }

        .slot-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f3f4f6;
        }

        .facility-badge {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .facility-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
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
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="overview.php" class="menu-link">
                            <img src="../asset/home.png" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reserverequests.php" class="menu-link">
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
                        <a href="quick-reservation.php" class="menu-link active">
                            <img src="../asset/quick.png" class="menu-icon">
                            <span class="menu-label">Quick Reservation</span>
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
                <a href="../adminside/log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <div class="main-content">
            <div class="page-header">Quick Reservation</div>
            <div class="page-subtitle">Create instant bookings for residents</div>
            
            <div class="facility-selector">
                <h3>Select a Facility</h3>
                <div class="facility-grid">
                    <div class="facility-card" data-facility="Chapel">
                        <img src="../asset/chapel.png" alt="Chapel">
                        <h5>Chapel</h5>
                    </div>
                    <div class="facility-card" data-facility="Basketball Court">
                        <img src="../asset/basketball.png" alt="Basketball">
                        <h5>Basketball Court</h5>
                    </div>
                    <div class="facility-card" data-facility="Multipurpose Hall">
                        <img src="../asset/multipurpose.png" alt="Hall">
                        <h5>Multipurpose Hall</h5>
                    </div>
                    <div class="facility-card" data-facility="Tennis Court">
                        <img src="../asset/tennis-court.png" alt="Tennis">
                        <h5>Tennis Court</h5>
                    </div>
                </div>
            </div>
            
            <div class="content-grid">
                <div class="calendar-section">
                    <h3>
                        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">calendar_month</span>
                        Calendar View
                        <span class="facility-badge" id="facilityBadge" style="display: none;">No facility selected</span>
                    </h3>
                    <div id="calendar"></div>
                </div>

                <div class="summary-section">
                    <h3>
                        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">event_note</span>
                        Current Bookings
                    </h3>
                    <div class="bookings-list" id="bookingsList">
                        <div class="empty-state">
                            <span class="material-symbols-outlined">event_busy</span>
                            <p>Select a facility to view bookings</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Reservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="selected_date">
                    <input type="hidden" id="selected_facility">
                    
                    <div class="alert alert-info">
                        <strong>Facility:</strong> <span id="display_facility"></span><br>
                        <strong>Selected Date:</strong> <span id="display_date"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" placeholder="09123456789" maxlength="11" required>
                        <div class="invalid-feedback">Please enter a valid Philippine mobile number (09XXXXXXXXX)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Time Slot <span class="text-danger">*</span></label>
                        <div class="time-slot-container">
                            <button type="button" class="btn slot-btn" data-start="08:00" data-end="09:00">8:00 AM - 9:00 AM</button>
                            <button type="button" class="btn slot-btn" data-start="09:00" data-end="10:00">9:00 AM - 10:00 AM</button>
                            <button type="button" class="btn slot-btn" data-start="10:00" data-end="11:00">10:00 AM - 11:00 AM</button>
                            <button type="button" class="btn slot-btn" data-start="11:00" data-end="12:00">11:00 AM - 12:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="12:00" data-end="13:00">12:00 PM - 1:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="13:00" data-end="14:00">1:00 PM - 2:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="14:00" data-end="15:00">2:00 PM - 3:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="15:00" data-end="16:00">3:00 PM - 4:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="16:00" data-end="17:00">4:00 PM - 5:00 PM</button>
                            <button type="button" class="btn slot-btn" data-start="17:00" data-end="18:00">5:00 PM - 6:00 PM</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label">Additional Notes (Optional)</label>
                        <textarea class="form-control" id="note" rows="3" placeholder="Purpose of reservation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitReservation" disabled>Submit Reservation</button>
                </div>
            </div>  
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let selectedFacility = null;
        let selectedTimeSlot = null;
        let currentBookings = [];

        $(document).ready(function() {
            // Initialize calendar
            initializeCalendar();

            // Facility card click
            $('.facility-card').on('click', function() {
                $('.facility-card').removeClass('selected');
                $(this).addClass('selected');
                selectedFacility = $(this).data('facility');
                
                $('#facilityBadge').text(selectedFacility).show();
                
                // Fetch bookings for selected facility
                fetchBookings(selectedFacility);
            });

            // Time slot selection
            $(document).on('click', '.slot-btn:not(.disabled)', function() {
                $('.slot-btn').removeClass('selected');
                $(this).addClass('selected');
                
                selectedTimeSlot = {
                    start: $(this).data('start'),
                    end: $(this).data('end')
                };
                
                validateForm();
            });

            // Phone input validation
            $('#phone').on('input', function() {
                let phone = $(this).val();
                // Remove non-numeric characters
                phone = phone.replace(/\D/g, '');
                // Limit to 11 digits
                if (phone.length > 11) {
                    phone = phone.substr(0, 11);
                }
                $(this).val(phone);
                validateForm();
            });

            // Form validation
            function validateForm() {
                const phone = $('#phone').val();
                const phoneValid = /^09\d{9}$/.test(phone);
                const timeSlotSelected = selectedTimeSlot !== null;
                
                if (!phoneValid && phone.length > 0) {
                    $('#phone').addClass('is-invalid');
                } else {
                    $('#phone').removeClass('is-invalid');
                }
                
                $('#submitReservation').prop('disabled', !(phoneValid && timeSlotSelected));
            }
        });

        function initializeCalendar() {
            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                editable: false,
                eventLimit: true,
                events: [],
                validRange: {
                    start: moment().format('YYYY-MM-DD')
                },
                dayClick: function(date) {
                    if (!selectedFacility) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Facility Selected',
                            text: 'Please select a facility first',
                            confirmButtonColor: '#3b82f6'
                        });
                        return;
                    }

                    const today = moment().startOf('day');
                    if (date.isBefore(today)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Cannot book past dates',
                            confirmButtonColor: '#3b82f6'
                        });
                        return;
                    }

                    openBookingModal(date);
                },
                eventClick: function(event) {
                    const statusColor = {
                        'confirmed': '#10b981',
                        'approved': '#3b82f6',
                        'pending': '#f59e0b'
                    };
                    
                    Swal.fire({
                        title: event.title,
                        html: `
                            <div style="text-align: left; padding: 10px;">
                                <p><strong>Date:</strong> ${moment(event.start).format('MMMM D, YYYY')}</p>
                                <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                                <p><strong>Status:</strong> <span style="color: ${statusColor[event.status] || '#6b7280'}; font-weight: 600;">${event.status.toUpperCase()}</span></p>
                                ${event.email ? `<p><strong>Email:</strong> ${event.email}</p>` : ''}
                                ${event.phone ? `<p><strong>Phone:</strong> ${event.phone}</p>` : ''}
                                ${event.note ? `<p><strong>Note:</strong> ${event.note}</p>` : ''}
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonColor: '#3b82f6'
                    });
                },
                eventRender: function(event, element) {
                    const statusColors = {
                        'confirmed': '#10b981',
                        'approved': '#3b82f6',
                        'pending': '#f59e0b'
                    };
                    
                    element.css('background-color', statusColors[event.status] || '#3b82f6');
                    element.css('border-color', statusColors[event.status] || '#3b82f6');
                }
            });
        }

        function fetchBookings(facility) {
            $('#calendar').fullCalendar('removeEvents');
            $('#bookingsList').html(`
                <div class="empty-state">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading bookings...</p>
                </div>
            `);
            
            $.ajax({
                url: 'quick-reservation.php',
                type: 'GET',
                data: {
                    action: 'fetch_bookings',
                    facility: facility
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status) {
                        currentBookings = response.data;
                        
                        $('#calendar').fullCalendar('removeEvents');
                        if (response.data.length > 0) {
                            $('#calendar').fullCalendar('addEventSource', response.data);
                        }
                        
                        updateBookingsList(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.msg || 'Failed to fetch bookings',
                            confirmButtonColor: '#3b82f6'
                        });
                        
                        $('#bookingsList').html(`
                            <div class="empty-state">
                                <span class="material-symbols-outlined">error</span>
                                <p>Failed to load bookings</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching bookings:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Unable to fetch bookings. Please try again.',
                        confirmButtonColor: '#3b82f6'
                    });
                    
                    $('#bookingsList').html(`
                        <div class="empty-state">
                            <span class="material-symbols-outlined">cloud_off</span>
                            <p>Connection error. Please refresh.</p>
                        </div>
                    `);
                }
            });
        }

        function updateBookingsList(bookings) {
            const $list = $('#bookingsList');
            
            if (bookings.length === 0) {
                $list.html(`
                    <div class="empty-state">
                        <span class="material-symbols-outlined">event_available</span>
                        <p>No bookings found for this facility</p>
                    </div>
                `);
                return;
            }

            bookings.sort((a, b) => new Date(b.start) - new Date(a.start));

            let html = '';
            bookings.forEach(booking => {
                const startTime = moment(booking.start).format('h:mm A');
                const endTime = moment(booking.end).format('h:mm A');
                const date = moment(booking.start).format('MMM D, YYYY');
                
                const statusColors = {
                    'confirmed': '#10b981',
                    'approved': '#3b82f6',
                    'pending': '#f59e0b'
                };
                
                const borderColor = statusColors[booking.status] || '#3b82f6';
                
                html += `
                    <div class="booking-item" style="border-left-color: ${borderColor};">
                        <h6>${booking.title}</h6>
                        <p><strong>Date:</strong> ${date}</p>
                        <p><strong>Time:</strong> ${startTime} - ${endTime}</p>
                        <p><strong>Status:</strong> <span style="color: ${borderColor}; font-weight: 600;">${booking.status.toUpperCase()}</span></p>
                        ${booking.phone ? `<p><strong>Phone:</strong> ${booking.phone}</p>` : ''}
                    </div>
                `;
            });
            
            $list.html(html);
        }

        function openBookingModal(date) {
            const formattedDate = date.format('YYYY-MM-DD');
            const displayDate = date.format('MMMM D, YYYY');
            
            $('#selected_date').val(formattedDate);
            $('#selected_facility').val(selectedFacility);
            $('#display_facility').text(selectedFacility);
            $('#display_date').text(displayDate);
            
            $('#phone').val('').removeClass('is-invalid');
            $('#note').val('');
            $('.slot-btn').removeClass('selected disabled').prop('disabled', false);
            selectedTimeSlot = null;
            $('#submitReservation').prop('disabled', true);
            
            checkAvailableSlots(formattedDate);
            
            $('#bookingModal').modal('show');
        }

        function checkAvailableSlots(date) {
            const bookedSlots = currentBookings
                .filter(b => moment(b.start).format('YYYY-MM-DD') === date)
                .map(b => ({
                    start: moment(b.start).format('HH:mm'),
                    end: moment(b.end).format('HH:mm')
                }));

            $('.slot-btn').each(function() {
                const slotStart = $(this).data('start');
                const slotEnd = $(this).data('end');
                
                const isBooked = bookedSlots.some(booked => {
                    return (slotStart >= booked.start && slotStart < booked.end) ||
                           (slotEnd > booked.start && slotEnd <= booked.end) ||
                           (slotStart <= booked.start && slotEnd >= booked.end);
                });
                
                if (isBooked) {
                    $(this).addClass('disabled').prop('disabled', true);
                }
            });
        }

        $('#submitReservation').on('click', function() {
            const phone = $('#phone').val();
            const note = $('#note').val();
            const date = $('#selected_date').val();
            const facility = $('#selected_facility').val();
            
            if (!selectedTimeSlot) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select a time slot',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            if (!/^09\d{9}$/.test(phone)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Phone Number',
                    text: 'Please enter a valid Philippine mobile number (e.g., 09123456789)',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            Swal.fire({
                title: 'Confirm Reservation',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>Facility:</strong> ${facility}</p>
                        <p><strong>Date:</strong> ${moment(date).format('MMMM D, YYYY')}</p>
                        <p><strong>Time:</strong> ${selectedTimeSlot.start} - ${selectedTimeSlot.end}</p>
                        <p><strong>Phone:</strong> ${phone}</p>
                        ${note ? `<p><strong>Note:</strong> ${note}</p>` : ''}
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Confirm Booking',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitReservation(facility, date, selectedTimeSlot.start, selectedTimeSlot.end, phone, note);
                }
            });
        });

        function submitReservation(facility, date, timeStart, timeEnd, phone, note) {
            $('#submitReservation').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...');
            
            $.ajax({
                url: 'quick-reservation.php',
                type: 'POST',
                data: {
                    action: 'create_reservation',
                    facility: facility,
                    date: date,
                    time_start: timeStart,
                    time_end: timeEnd,
                    phone: phone,
                    note: note
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Reservation created successfully',
                            confirmButtonColor: '#3b82f6'
                        }).then(() => {
                            $('#bookingModal').modal('hide');
                            fetchBookings(selectedFacility);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to create reservation',
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error submitting reservation:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to submit reservation. Please try again.',
                        confirmButtonColor: '#3b82f6'
                    });
                },
                complete: function() {
                    $('#submitReservation').prop('disabled', false).html('Submit Reservation');
                }
            });
        }
    </script>
</body>
</html>