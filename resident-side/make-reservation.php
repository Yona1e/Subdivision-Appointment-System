<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Resident') {
    header("Location: ../login/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="make-reservation.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.css">

    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>

    <title>Sidebar Demo</title>
    <!-- Google Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>

<body>

    <div class="app-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <img src="../asset/logo.png" alt="Header Logo" class="header-logo">
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">
                        chevron_left
                    </span>
                </button>
            </header>
            <div class="sidebar-content">
                <!-- Menu List -->
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="#" class="menu-link active">
                            <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                            <span class="menu-label">Home</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/makeareservation.png" alt="Make a Reservation Icon" class="menu-icon">
                            <span class="menu-label">Make a Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/bell.png" alt="My Balance Icon" class="menu-icon">
                            <span class="menu-label">My Balance</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">My Account</span>
                        </a>
                    </li>
                </ul>
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
                        <span class="circle active"><img src="../asset/facility.png"></span>
                        <span class="circle"><img src="../asset/date-time.png"></span>
                        <span class="circle"><img src="../asset/payment.png"></span>
                        <div class="progress-bar">
                            <span class="indicator"></span>
                        </div>
                    </div>
                    <!-- FACILITY -->
                    <div class="card-body facility">
                        <div class="row row-cols-1 row-cols-md-4 g-4">
                            <div class="col chapel">
                                <a href="chapel.html" class="card-link">
                                    <div class="card h-100">
                                        <img src="../asset/chapel.png" class="card-img-top" alt="chapel">
                                        <div class="card-body content">
                                            <h5 class="card-title">Chapel</h5>
                                            <p class="card-text">Lorem ipsum dolor sit amet consectetur adipiscing elit.
                                                Dolor
                                                sit amet consectetur adipiscing elit quisque faucibus.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col basketball">
                                <a href="chapel.html" class="card-link">
                                    <div class="card h-100">
                                        <img src="../asset/basketball.png" class="card-img-top" alt="...">
                                        <div class="card-body content">
                                            <h5 class="card-title">Basketball Court</h5>
                                            <p class="card-text">Lorem ipsum dolor sit amet consectetur adipiscing elit.
                                                Dolor
                                                sit amet consectetur adipiscing elit quisque faucibus.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col hall">
                                <a href="chapel.html" class="card-link">
                                    <div class="card h-100">

                                        <img src="../asset/multi-purpose.png" class="card-img-top" alt="...">
                                        <div class="card-body content">
                                            <h5 class="card-title">Multipurpose Hall</h5>
                                            <p class="card-text">Lorem ipsum dolor sit amet consectetur adipiscing elit.
                                                Dolor
                                                sit amet consectetur adipiscing elit quisque faucibus.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col tennis">
                                <a href="chapel.html" class="card-link">
                                    <div class="card h-100">

                                        <img src="../asset/tennis-court.png" class="card-img-top" alt="...">
                                        <div class="card-body content">
                                            <h5 class="card-title">Tennis Court</h5>
                                            <p class="card-text">Lorem ipsum dolor sit amet consectetur adipiscing elit.
                                                Dolor
                                                sit amet consectetur adipiscing elit quisque faucibus.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- DATE AND TIME -->
                    <div class="calendar-wrapper">
                        <div id="calendar"></div>
                    </div>
                    <!-- Modal -->
                    <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalLabel">Book a Facility</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">

                                    <!-- Phone Number -->
                                    <div class="form-group">
                                        <label for="phone">Enter a phone number:</label>
                                        <input type="text" class="form-control mb-2" id="phone" placeholder="123456789"
                                            maxlength="11">
                                        <div class="invalid-feedback" name="number" id="phoneFeedback">
                                            Please enter numbers only.
                                        </div>
                                    </div>

                                    <!-- Time -->
                                    <div class="form-group">
                                        <div class="card time-slot-card">
                                            <div class="card-header fw-bold">
                                                Available Time Slots
                                            </div>

                                            <div class="card-body">
                                                <p class="text-muted small mb-3">
                                                    ⓘ Each slot: 30 mins | Break: 10 mins
                                                </p>

                                                <div class="slots-container">
                                                    <button class="btn slot-btn" data-start="08:00" data-end="09:00"
                                                        name="time">
                                                        8:00 AM - 9:00 AM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="09:00" data-end="10:00"
                                                        name="time">
                                                        9:00 AM - 10:00 AM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="10:00" data-end="11:00"
                                                        name="time">
                                                        10:00 AM - 11:00 AM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="11:00" data-end="12:00"
                                                        name="time">
                                                        11:00 AM - 12:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="12:00" data-end="13:00"
                                                        name="time">
                                                        12:00 PM - 1:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="13:00" data-end="14:00"
                                                        name="time">
                                                        1:00 PM - 2:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="14:00" data-end="15:00"
                                                        name="time">
                                                        2:00 PM - 3:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="15:00" data-end="16:00"
                                                        name="time">
                                                        3:00 PM - 4:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="16:00" data-end="17:00"
                                                        name="time">
                                                        4:00 PM - 5:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="17:00" data-end="18:00"
                                                        name="time">
                                                        5:00 PM - 6:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="18:00" data-end="19:00"
                                                        name="time">
                                                        6:00 PM - 7:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="19:00" data-end="20:00" v>
                                                        7:00 PM - 8:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="20:00" data-end="21:00"
                                                        name="time">
                                                        8:00 PM - 9:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="21:00" data-end="22:00"
                                                        name="time">
                                                        9:00 PM - 10:00 PM
                                                    </button>

                                                    <button class="btn slot-btn" data-start="22:00" data-end="23:00"
                                                        name="time">
                                                        10:00 PM - 11:00 PM
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--  NOTE -->
                                    <div class="form-group">
                                        <label for="floatingTextarea2">Note</label>
                                        <textarea class="form-control" placeholder="Leave a note here"
                                            id="floatingTextarea2" style="height: 100px"></textarea>
                                        
                                    </div>
                                </div>
                                <!-- Button -->
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                    <button type="button" id="saveEvent" name="save-event"
                                        class="btn btn-primary">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </form>
                    <div class="buttons">
                        <button class="btn btn-primary" type="button" id="prev">Prev</button>
                        <button class="btn btn-primary" type="button" id="next">Next</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS (with Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JS -->
    <script src="javascript/calendar.js"></script>
    <script src="javascript/sidebar.js"></script>
    <script src="javascript/progress-btn.js"></script>

</body>

</html>