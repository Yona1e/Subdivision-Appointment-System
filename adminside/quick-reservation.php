<?php
session_start();
// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Database connection
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

// Handle AJAX request for bookings data
if (isset($_GET['action']) && $_GET['action'] === 'fetch_bookings') {
    $facility = isset($_GET['facility']) ? trim($_GET['facility']) : null;
    
    if (!$facility || empty($facility)) {
        echo json_encode(['status' => false, 'msg' => 'No facility selected', 'data' => []]);
        exit();
    }
    
    try {
        // Fetch bookings with resident information for the selected facility
        $sql = "SELECT 
                    r.reservation_id,
                    r.facility_name,
                    r.event_start_date as date,
                    r.time_start,
                    r.time_end,
                    r.status,
                    r.phone,
                    r.note,
                    CONCAT(u.FirstName, ' ', u.LastName) as resident_name,
                    u.Email as resident_email
                FROM reservations r
                INNER JOIN users u ON r.user_id = u.user_id
                WHERE r.facility_name = :facility
                AND r.status IN ('confirmed', 'approved', 'pending')
                ORDER BY r.event_start_date DESC, r.time_start DESC
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':facility', $facility, PDO::PARAM_STR);
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            // Format time from 24h to 12h
            $timeStart = date('g:i A', strtotime($booking['time_start']));
            $timeEnd = date('g:i A', strtotime($booking['time_end']));
            
            $formattedBookings[] = [
                'reservation_id' => $booking['reservation_id'],
                'resident_name' => $booking['resident_name'],
                'resident_email' => $booking['resident_email'],
                'date' => $booking['date'],
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'status' => ucfirst($booking['status']),
                'phone' => $booking['phone'],
                'note' => $booking['note']
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

// Fetch current user data
$user_id = $_SESSION['user_id'];
$userStmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists
if (!$user) {
    session_destroy();
    header("Location: ../login/login.php");
    exit();
}

// Profile picture fallback
$profilePic = !empty($user['ProfilePictureURL'])
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

$userName = htmlspecialchars(trim($user['FirstName'] . ' ' . $user['LastName']));

// Define variables for sidebar
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <!-- FullCalendar v3 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            color: var(--gray-900);
            overflow-x: hidden;
        }
        
        .app-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }
        
        .page-header {
            font-family: 'Syne', sans-serif;
            font-size: 48px;
            font-weight: 800;
            color: white;
            margin-bottom: 16px;
            text-shadow: 0 4px 20px rgba(0,0,0,0.2);
            letter-spacing: -0.02em;
            animation: slideDown 0.6s ease-out;
        }
        
        .page-subtitle {
            font-size: 18px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 40px;
            font-weight: 400;
            animation: slideDown 0.6s ease-out 0.1s backwards;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Facility Selector */
        .facility-selector {
            background: white;
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            animation: fadeInUp 0.6s ease-out 0.2s backwards;
        }
        
        .facility-selector h3 {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--gray-900);
            position: relative;
            display: inline-block;
        }
        
        .facility-selector h3::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }
        
        .facility-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .facility-card {
            background: var(--gray-50);
            border: 3px solid transparent;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }
        
        .facility-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }
        
        .facility-card > * {
            position: relative;
            z-index: 1;
        }
        
        .facility-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);
        }
        
        .facility-card.selected {
            border-color: var(--primary);
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 50px rgba(99, 102, 241, 0.4);
        }
        
        .facility-card.selected::before {
            opacity: 0.1;
        }
        
        .facility-card img {
            width: 70px;
            height: 70px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.1));
            transition: transform 0.4s ease;
        }
        
        .facility-card:hover img {
            transform: scale(1.1) rotate(5deg);
        }
        
        .facility-card h5 {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--gray-900);
            transition: color 0.3s ease;
        }
        
        .facility-card.selected h5 {
            color: var(--primary);
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
            animation: fadeInUp 0.6s ease-out 0.3s backwards;
        }
        
        /* Bookings Table Section */
        .bookings-section {
            background: white;
            border-radius: 24px;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            display: none;
        }
        
        .bookings-section.active {
            display: block;
            animation: scaleIn 0.4s ease-out;
        }
        
        .bookings-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 30px 35px;
            color: white;
        }
        
        .bookings-header h3 {
            font-family: 'Syne', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .facility-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .bookings-body {
            padding: 0;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .bookings-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .bookings-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--gray-50);
        }
        
        .bookings-table th {
            padding: 20px 25px;
            text-align: left;
            font-family: 'Syne', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
        }
        
        .bookings-table td {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 14px;
            color: var(--gray-700);
            transition: background 0.2s ease;
        }
        
        .bookings-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .bookings-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .resident-name {
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .resident-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-badge.confirmed {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .empty-state {
            padding: 60px 40px;
            text-align: center;
            color: var(--gray-400);
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state p {
            font-size: 16px;
            margin: 0;
        }
        
        /* Calendar Section */
        .calendar-section {
            background: white;
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .calendar-section h3 {
            font-family: 'Syne', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--gray-900);
        }
        
        #calendar {
            max-width: 100%;
        }
        
        /* FullCalendar Customization */
        .fc-toolbar h2 {
            font-family: 'Syne', sans-serif !important;
            font-size: 24px !important;
            font-weight: 700 !important;
            color: var(--gray-900) !important;
        }
        
        .fc-button {
            background: var(--primary) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 8px 16px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .fc-button:hover {
            background: var(--primary-dark) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
        }
        
        .fc-day-grid-event {
            padding: 4px 6px;
            font-size: 12px;
            border-radius: 6px;
            font-weight: 600;
            border: none !important;
        }
        
        .fc-event {
            border-radius: 6px !important;
        }
        
        /* Modal Styling */
        .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 30px 35px;
        }
        
        .modal-title {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 700;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        
        .modal-body {
            padding: 35px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(245, 158, 11, 0.1));
            border: 2px solid var(--primary-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .time-slot-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .slot-btn {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--gray-200);
            background: white;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
        }
        
        .slot-btn:hover:not(:disabled) {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
            transform: translateY(-2px);
        }
        
        .slot-btn.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .slot-btn:disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .modal-footer {
            border: none;
            padding: 25px 35px;
            background: var(--gray-50);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            border: none;
            color: var(--gray-700);
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        /* Scrollbar Styling */
        .bookings-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .bookings-body::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        .bookings-body::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }
        
        .bookings-body::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .page-header {
                font-size: 32px;
            }
            
            .facility-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .time-slot-container {
                grid-template-columns: 1fr;
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
                        <p class="profile-name">
                            <?= htmlspecialchars($loggedInUserName) ?>
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
                        <a href="create-account.php" class="menu-link ">
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

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="page-header">Quick Reservation</div>
            <div class="page-subtitle">Create instant bookings for residents</div>
            
            <!-- Facility Selector -->
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
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Bookings Table -->
                <div class="bookings-section" id="bookingsSection">
                    <div class="bookings-header">
                        <h3>
                            <span class="material-symbols-outlined">event_note</span>
                            Current Bookings
                            <span class="facility-badge" id="facilityBadge"></span>
                        </h3>
                    </div>
                    <div class="bookings-body">
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Resident</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Calendar Section -->
                <div class="calendar-section">
                    <h3>
                        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">calendar_month</span>
                        Select Date
                    </h3>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
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
    <script src="javascript/sidebar.js"></script>
    
    <script>
        var selectedFacility = null;
        var allEvents = [];
        var selectedTimeSlot = null;
        
        $(document).ready(function() {
            loadCalendar();
            
            // Facility selection
            $('.facility-card').on('click', function() {
                $('.facility-card').removeClass('selected');
                $(this).addClass('selected');
                selectedFacility = $(this).data('facility');
                
                // Show bookings section
                $('#bookingsSection').addClass('active');
                $('#facilityBadge').text(selectedFacility);
                
                // Load bookings data and calendar
                loadBookingsData(selectedFacility);
                loadCalendar();
            });
            
            // Time slot selection
            $(document).on('click', '.slot-btn', function() {
                if ($(this).prop('disabled')) return;
                
                $('.slot-btn').removeClass('selected');
                $(this).addClass('selected');
                
                selectedTimeSlot = {
                    start: $(this).data('start'),
                    end: $(this).data('end')
                };
                
                checkFormCompletion();
            });
            
            // Phone validation
            $('#phone').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                $(this).val(value);
                validatePhone(value);
                checkFormCompletion();
            });
            
            // Submit reservation
            $('#submitReservation').on('click', function() {
                submitReservation();
            });
        });
        
        function loadBookingsData(facility) {
            $.ajax({
                url: 'quick-reservation.php',
                method: 'GET',
                data: { 
                    action: 'fetch_bookings',
                    facility: facility 
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Bookings data:', response); // Debug log
                    
                    var tbody = $('#bookingsTableBody');
                    tbody.empty();
                    
                    if (response.status && response.data.length > 0) {
                        response.data.forEach(function(booking) {
                            var initials = getInitials(booking.resident_name);
                            var statusClass = booking.status.toLowerCase();
                            
                            var row = `
                                <tr>
                                    <td>
                                        <div class="resident-name">
                                            <div class="resident-avatar">${initials}</div>
                                            ${booking.resident_name}
                                        </div>
                                    </td>
                                    <td>${moment(booking.date).format('MMM DD, YYYY')}</td>
                                    <td>${booking.time_start} - ${booking.time_end}</td>
                                    <td><span class="status-badge ${statusClass}">${booking.status}</span></td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                    } else {
                        tbody.html(`
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📋</div>
                                        <p>No bookings found for this facility</p>
                                    </div>
                                </td>
                            </tr>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Bookings loading error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    $('#bookingsTableBody').html(`
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div class="empty-state-icon">⚠️</div>
                                    <p>Error loading bookings data</p>
                                </div>
                            </td>
                        </tr>
                    `);
                }
            });
        }
        
        function getInitials(name) {
            return name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2);
        }
        
        function loadCalendar() {
            $.ajax({
                url: 'fetch_reservations.php',
                method: 'GET',
                data: { facility: selectedFacility },
                dataType: 'json',
                success: function(response) {
                    console.log('Calendar data received:', response); // Debug log
                    
                    allEvents = response.data || [];
                    
                    console.log('Total events:', allEvents.length); // Debug log
                    
                    $('#calendar').fullCalendar('destroy');
                    $('#calendar').fullCalendar({
                        header: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'month'
                        },
                        selectable: true,
                        selectHelper: true,
                        events: allEvents,
                        eventRender: function(event, element) {
                            // Add tooltip with more info
                            element.attr('title', event.resident + ' - ' + event.status);
                        },
                        select: function(start, end) {
                            if (!selectedFacility) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Select a Facility',
                                    text: 'Please select a facility first',
                                    confirmButtonColor: '#6366f1'
                                });
                                $('#calendar').fullCalendar('unselect');
                                return;
                            }
                            
                            var selectedDate = moment(start).format('YYYY-MM-DD');
                            var today = moment().format('YYYY-MM-DD');
                            
                            if (selectedDate < today) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Invalid Date',
                                    text: 'Cannot book past dates',
                                    confirmButtonColor: '#6366f1'
                                });
                                $('#calendar').fullCalendar('unselect');
                                return;
                            }
                            
                            openBookingModal(start);
                        },
                        eventClick: function(event) {
                            var status = event.status || 'confirmed';
                            Swal.fire({
                                title: event.title,
                                html: `<strong>Resident:</strong> ${event.resident}<br>
                                       <strong>Date:</strong> ${moment(event.start).format('MMMM DD, YYYY')}<br>
                                       <strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}<br>
                                       <strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)}`,
                                icon: 'info',
                                confirmButtonColor: '#6366f1'
                            });
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Calendar loading error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Loading Calendar',
                        text: 'Failed to load reservation data',
                        confirmButtonColor: '#6366f1'
                    });
                }
            });
        }
        
        function openBookingModal(start) {
            var date = moment(start).format('YYYY-MM-DD');
            
            $('#selected_date').val(date);
            $('#selected_facility').val(selectedFacility);
            $('#display_facility').text(selectedFacility);
            $('#display_date').text(moment(start).format('MMMM DD, YYYY'));
            
            // Reset form
            $('#phone').val('').removeClass('is-valid is-invalid');
            $('#note').val('');
            $('.slot-btn').removeClass('selected').prop('disabled', false).css('opacity', '1');
            selectedTimeSlot = null;
            $('#submitReservation').prop('disabled', true);
            
            // Check booked slots
            checkBookedSlots(date, selectedFacility);
            
            $('#bookingModal').modal('show');
        }
        
        function checkBookedSlots(date, facility) {
            var bookedSlots = allEvents.filter(function(event) {
                var eventDate = moment(event.start).format('YYYY-MM-DD');
                return event.title === facility && eventDate === date;
            });
            
            console.log('Booked slots for ' + date + ':', bookedSlots); // Debug log
            
            var now = moment();
            var isToday = date === now.format('YYYY-MM-DD');
            
            $('.slot-btn').each(function() {
                var slotStart = $(this).data('start');
                var slotEnd = $(this).data('end');
                var slotTime = moment(date + ' ' + slotStart, 'YYYY-MM-DD HH:mm');
                
                // Check if past time
                if (isToday && slotTime.isBefore(now)) {
                    $(this).prop('disabled', true).css('opacity', '0.5');
                    return;
                }
                
                // Check if booked
                var isBooked = bookedSlots.some(function(event) {
                    var eventStart = moment(event.start);
                    var eventEnd = moment(event.end);
                    var checkStart = moment(date + ' ' + slotStart, 'YYYY-MM-DD HH:mm');
                    var checkEnd = moment(date + ' ' + slotEnd, 'YYYY-MM-DD HH:mm');
                    
                    return checkStart.isBefore(eventEnd) && checkEnd.isAfter(eventStart);
                });
                
                if (isBooked) {
                    $(this).prop('disabled', true).css('opacity', '0.5');
                }
            });
        }
        
        function validatePhone(phone) {
            var $input = $('#phone');
            
            if (phone.length === 0) {
                $input.removeClass('is-valid is-invalid');
                return false;
            }
            
            if (phone.length !== 11 || !phone.startsWith('09') || /^(\d)\1{10}$/.test(phone)) {
                $input.addClass('is-invalid').removeClass('is-valid');
                return false;
            }
            
            $input.removeClass('is-invalid').addClass('is-valid');
            return true;
        }
        
        function checkFormCompletion() {
            var phone = $('#phone').val();
            var isValid = validatePhone(phone) && selectedTimeSlot !== null;
            
            $('#submitReservation').prop('disabled', !isValid);
        }
        
        function submitReservation() {
            var facility = $('#selected_facility').val();
            var date = $('#selected_date').val();
            var phone = $('#phone').val();
            var note = $('#note').val();
            var timeStart = selectedTimeSlot.start;
            var timeEnd = selectedTimeSlot.end;
            
            var $btn = $('#submitReservation');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
            
            $.ajax({
                url: 'save_quick_reservation.php',
                method: 'POST',
                data: {
                    facility_name: facility,
                    phone: phone,
                    event_start_date: date,
                    event_end_date: date,
                    time_start: timeStart,
                    time_end: timeEnd,
                    note: note
                },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html('Submit Reservation');
                    
                    if (response.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reservation Confirmed!',
                            text: 'Your reservation has been automatically approved.',
                            confirmButtonColor: '#6366f1'
                        }).then(() => {
                            $('#bookingModal').modal('hide');
                            loadCalendar();
                            loadBookingsData(selectedFacility);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.msg || 'Failed to save reservation',
                            confirmButtonColor: '#6366f1'
                        });
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('Submit Reservation');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.',
                        confirmButtonColor: '#6366f1'
                    });
                }
            });
        }
    </script>
    <script src="../resident-side/javascript/sidebar.js"></script>
</body>
</html>