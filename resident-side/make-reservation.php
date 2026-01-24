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

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

// fetch current user data for sidebar
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

// Verify the file exists, otherwise use default
if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

// User's full name for sidebar
$userName = htmlspecialchars(trim($user['FirstName'] . ' ' . $user['LastName']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="make-reservation1.css">
    <link rel="stylesheet" href="style/side-navigation1.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery (MUST be loaded first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>

    <!-- FullCalendar v3 CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>

    <title>Make a Reservation</title>
    <!-- Google Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
                            <p class="profile-name"><?= $userName ?></p>
                            <p class="profile-role">Resident</p>
                        </div>
                    </div>
                </a>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>
            <div class="sidebar-content">
                <!-- Menu List -->
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="../home/home.php" class="menu-link">
                            <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                            <span class="menu-label">Home</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="make-reservation.php" class="menu-link active">
                            <img src="../asset/makeareservation.png" alt="Make a Reservation Icon" class="menu-icon">
                            <span class="menu-label">Make a Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../my-reservations/myreservations.php" class="menu-link">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">My Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../my-account/my-account.php" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">My Account</span>
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
            <!-- RESERVATION CARD -->
            <div class="reservation-card">
                <div class="page-header">
                    Make a Reservation
                </div>
                <!-- PROGRESS STEP BAR -->
                <div class="container">
                    <div class="steps">
                        <div class="step-wrapper">
                            <span class="circle active">
                                <img src="../asset/facility1.png" alt="Facility">
                            </span>
                            <span class="step-label">Select Facility</span>
                        </div>

                        <div class="step-wrapper">
                            <span class="circle">
                                <img src="../asset/date-time1.png" alt="Date Time">
                            </span>
                            <span class="step-label">Date & Time</span>
                        </div>

                        <div class="step-wrapper">
                            <span class="circle">
                                <img src="../asset/payment1.png" alt="Payment">
                            </span>
                            <span class="step-label">Payment</span>
                        </div>

                        <div class="progress-bar">
                            <span class="indicator"></span>
                        </div>
                    </div>

                    <!-- FACILITY -->
                    <div class="card-body facility">
                        <div class="row row-cols-1 row-cols-md-4 g-5">
                            <div class="col chapel">
                                <a href="#" class="card-link" data-facility="Chapel">
                                    <div class="card h-100">
                                        <img src="../asset/chapel.png" class="card-img-top" alt="chapel">
                                        <div class="card-body content">
                                            <h5 class="card-title">Chapel</h5>
                                            <p class="card-text">The Catholic Chapel provides a peaceful and sacred
                                                venue for weddings, baptisms, thanksgiving masses, memorial services,
                                                and other religious ceremonies. Its calm atmosphere makes it suitable
                                                for a wide range of spiritual events.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col basketball">
                                <a href="#" class="card-link" data-facility="Basketball Court">
                                    <div class="card h-100">
                                        <img src="../asset/basketball.png" class="card-img-top" alt="basketball">
                                        <div class="card-body content">
                                            <h5 class="card-title">Basketball Court</h5>
                                            <p class="card-text">The basketball court is a full-size area designed for
                                                games, practice sessions, sports events, and friendly tournaments. Its
                                                open layout supports smooth play and allows for organized or
                                                recreational basketball activities.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col hall">
                                <a href="#" class="card-link" data-facility="Multipurpose Hall">
                                    <div class="card h-100">
                                        <img src="../asset/multipurpose.png" class="card-img-top" alt="hall">
                                        <div class="card-body content">
                                            <h5 class="card-title">Multipurpose Hall</h5>
                                            <p class="card-text">The multipurpose hall offers a flexible and spacious
                                                venue for meetings, parties, events, workshops, and community programs.
                                                Its adaptable layout makes it suitable for a wide range of gatherings
                                                and organized activities.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col tennis">
                                <a href="#" class="card-link" data-facility="Tennis Court">
                                    <div class="card h-100">
                                        <img src="../asset/tennis-court.png" class="card-img-top" alt="tennis">
                                        <div class="card-body content">
                                            <h5 class="card-title">Tennis Court</h5>
                                            <p class="card-text">The tennis court provides a smooth and well-maintained
                                                surface for tennis matches and practice. It forms part of the
                                                recreational area in the subdivision and is ideal for outdoor
                                                activities, fitness, and leisure play.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="container py-5" style="display: block;padding-top: 30px !important;">
                        <div class="row g-4">
                            <!-- Left Side - Calendar -->
                            <div class="col-lg-7">
                                <div class="calendar-wrapper">
                                    <div id="calendar"></div>
                                </div>
                            </div>

                            <!-- Right Side - Summary Section -->
                            <div class="col-lg-5">
                                <div class="summary-card">
                                    <div class="summary-header">
                                        <h3 class="summary-title">Summary</h3>
                                    </div>

                                    <div class="summary-body">
                                        <h4 class="facility-name">Basketball Court</h4>
                                        <p class="reservation-datetime">January 13, 9:00AM - 11:00AM</p>

                                        <hr class="my-4">

                                        <div class="cost-breakdown mb-3">
                                            <h5 class="breakdown-title">Cost Breakdown</h5>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="breakdown-item">Basketball Reservation -<br>January 13,
                                                    9:00AM - 11:00AM</span>
                                                <span class="breakdown-price">₱700.00</span>
                                            </div>
                                        </div>

                                        <hr class="my-3">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="total-label">Total Cost:</strong>
                                            <strong class="total-price">₱700.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Modal -->
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
                    <!-- Payment -->
                    <div class="container py-5 payment">
                        <div class="row g-4">
                            <!-- Left Side - Payment Section -->
                            <div class="col-lg-7">
                                <div class="payment-card">
                                    <h2 class="payment-title">Pay using GCASH</h2>
                                    <p class="payment-instruction">
                                        In order to secure your slot, please send the full payment to
                                        <span class="gcash-number">09123456789</span>
                                        <strong>** T**</strong> and upload a screenshot of your proof of payment.
                                    </p>
                                    <div class="upload-area" id="uploadArea">
                                        <div class="upload-icon">
                                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                        </div>
                                        <p class="upload-text mb-0">Drag your file here</p>
                                        <p class="upload-subtext">or click to browse</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side - Summary Section -->
                            <div class="col-lg-5">
                                <div class="summary-card">
                                    <div class="summary-header">
                                        <h3 class="summary-title">Summary</h3>
                                    </div>

                                    <div class="summary-body">
                                        <h4 class="facility-name">Basketball Court</h4>
                                        <p class="reservation-datetime">January 13, 9:00AM - 11:00AM</p>

                                        <hr class="my-4">

                                        <div class="cost-breakdown mb-3">
                                            <h5 class="breakdown-title">Cost Breakdown</h5>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="breakdown-item">Basketball Reservation -<br>January 13,
                                                    9:00AM - 11:00AM</span>
                                                <span class="breakdown-price">₱700.00</span>
                                            </div>
                                        </div>

                                        <hr class="my-3">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="total-label">Total Cost:</strong>
                                            <strong class="total-price">₱700.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Templates for File Upload -->
                    <!-- Template: Default Upload State -->
                    <div id="uploadDefaultTemplate" style="display: none;">
                        <div class="upload-icon">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <p class="upload-text mb-0">Drag your file here</p>
                        <p class="upload-subtext">or click to browse</p>
                    </div>

                    <!-- Template: Image Preview State -->
                    <div id="uploadPreviewTemplate" style="display: none;">
                        <div class="upload-preview">
                            <div class="preview-image-container">
                                <img src="" alt="Preview" class="preview-image">
                            </div>
                            <div class="preview-info">
                                <p class="preview-filename"><strong>File:</strong> <span class="filename-text"></span></p>
                                <p class="preview-filesize"><strong>Size:</strong> <span class="filesize-text"></span></p>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm remove-file-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                Remove
                            </button>
                        </div>
                    </div>

                    <div class="footer-buttons">
                        <button class="btn " type="button" id="prev">Prev</button>
                        <button class="btn" type="button" id="next">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS (with Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <!-- Other JS -->
    <script src="javascript/reservation.js"></script>
    <script src="javascript/sidebar.js"></script>
    <script src="javascript/progress-btn.js"></script>
    <script src="javascript/drag-file.js"></script>

</body>

</html>