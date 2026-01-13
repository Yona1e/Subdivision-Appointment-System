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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                        <span class="circle active"><img src="../asset/facility.png" alt="Facility"></span>
                        
                        <span class="circle"><img src="../asset/date-time.png" alt="Date Time"></span>
                        <span class="circle"><img src="../asset/payment.png" alt="Payment"></span>
                        <div class="progress-bar">
                            <span class="indicator"></span>
                        </div>
                    </div>
                    
                    <!-- FACILITY -->
                    <div class="card-body facility">
                        <div class="row row-cols-1 row-cols-md-4 g-4">
                            <div class="col chapel">
                                <a href="#" class="card-link" data-facility="Chapel">
                                    <div class="card h-100">
                                        <img src="../asset/chapel.png" class="card-img-top" alt="chapel">
                                        <div class="card-body content">
                                            <h5 class="card-title">Chapel</h5>
                                            <p class="card-text">Perfect for weddings, baptisms, and religious ceremonies in a peaceful setting.</p>
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
                                            <p class="card-text">Full-size court for basketball games, tournaments, and sports activities.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col hall">
                                <a href="#" class="card-link" data-facility="Multipurpose Hall">
                                    <div class="card h-100">
                                        <img src="../asset/multi-purpose.png" class="card-img-top" alt="hall">
                                        <div class="card-body content">
                                            <h5 class="card-title">Multipurpose Hall</h5>
                                            <p class="card-text">Spacious hall for events, parties, meetings, and community gatherings.</p>
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
                                            <p class="card-text">Professional tennis court for matches, practice, and tennis lessons.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- DATE AND TIME CALENDAR -->
                    <div class="calendar-wrapper">
                        <div id="calendar"></div>
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
                                        <label for="facility_name">Facility Name: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="facility_name" readonly 
                                               style="background-color: #e9ecef; cursor: not-allowed;">
                                        <small class="form-text text-muted">
                                            Please select a facility from the cards above before choosing a date.
                                        </small>
                                    </div>

                                    <!-- Phone Number -->
                                    <div class="form-group mb-3">
                                        <label for="phone">Phone Number: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="phone" placeholder="09123456789" maxlength="11" required>
                                        <div class="invalid-feedback" id="phoneFeedback" style="display: none;">
                                            Please enter numbers only (10-11 digits).
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
                                                    <button type="button" class="btn slot-btn" data-start="08:00" data-end="09:00">
                                                        8:00 AM - 9:00 AM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="09:00" data-end="10:00">
                                                        9:00 AM - 10:00 AM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="10:00" data-end="11:00">
                                                        10:00 AM - 11:00 AM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="11:00" data-end="12:00">
                                                        11:00 AM - 12:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="12:00" data-end="13:00">
                                                        12:00 PM - 1:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="13:00" data-end="14:00">
                                                        1:00 PM - 2:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="14:00" data-end="15:00">
                                                        2:00 PM - 3:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="15:00" data-end="16:00">
                                                        3:00 PM - 4:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="16:00" data-end="17:00">
                                                        4:00 PM - 5:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="17:00" data-end="18:00">
                                                        5:00 PM - 6:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="18:00" data-end="19:00">
                                                        6:00 PM - 7:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="19:00" data-end="20:00">
                                                        7:00 PM - 8:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="20:00" data-end="21:00">
                                                        8:00 PM - 9:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="21:00" data-end="22:00">
                                                        9:00 PM - 10:00 PM
                                                    </button>
                                                    <button type="button" class="btn slot-btn" data-start="22:00" data-end="23:00">
                                                        10:00 PM - 11:00 PM
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- NOTE -->
                                    <div class="form-group mb-3">
                                        <label for="event_note">Additional Notes (Optional)</label>
                                        <textarea class="form-control" placeholder="Leave a note here (e.g., purpose of reservation, special requests)"
                                            id="event_note" rows="3"></textarea>
                                    </div>
                                </div>
                                
                                <!-- Button -->
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="saveReservationBtn">
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                        Save Reservation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="buttons">
                        <button class="btn" type="button" id="prev">Prev</button>
                        <button class="btn" type="button" id="next">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS (with Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Calendar and Reservation JavaScript -->
    <script>
    // Global variable to store selected facility
    var selectedFacility = null;
    
    $(document).ready(function () {
        // Initialize calendar when page loads
        load_events();
        
        // Facility card selection - FIXED
        $('.card-link').on('click', function(e) {
            e.preventDefault();
            
            // Remove selected class from all
            $('.col').removeClass('selected');
            
            // Add selected class to clicked facility
            $(this).parent().addClass('selected');
            
            // Store facility name globally
            selectedFacility = $(this).data('facility');
            
            console.log("Facility selected:", selectedFacility); // Debug log
            
            // Reload calendar to show only this facility's events
            load_events();
        });
        
        // Time slot selection handler
        $(document).on('click', '.slot-btn', function() {
            // Remove selected class from all buttons
            $('.slot-btn').removeClass('selected');
            
            // Add selected class to clicked button
            $(this).addClass('selected');
            
            // Store selected time in hidden fields
            $('#selected_time_start').val($(this).data('start'));
            $('#selected_time_end').val($(this).data('end'));
        });
        
        // Phone number validation (only numbers)
        $('#phone').on('input', function() {
            var value = $(this).val();
            
            // Remove non-numeric characters
            var cleaned = value.replace(/\D/g, '');
            $(this).val(cleaned);
            
            // Validate phone number
            if (cleaned.length < 10 || cleaned.length > 11) {
                $(this).addClass('is-invalid');
                $('#phoneFeedback').show();
            } else {
                $(this).removeClass('is-invalid');
                $('#phoneFeedback').hide();
            }
        });
        
        // Save reservation button click handler
        $('#saveReservationBtn').on('click', function() {
            save_event();
        });
    });

    /**
     * Load events from database and initialize calendar
     */
    function load_events() {
        $.ajax({
            url: "display_event.php",
            dataType: "json",
            success: function (response) {
                // Destroy existing calendar instance
                $('#calendar').fullCalendar('destroy');

                // Filter events based on selected facility
                var filteredEvents = [];
                if (response.data && response.data.length > 0) {
                    if (selectedFacility) {
                        // Show only events for the selected facility
                        filteredEvents = response.data.filter(function(event) {
                            return event.title === selectedFacility;
                        });
                    } else {
                        // If no facility selected, show all events
                        filteredEvents = response.data;
                    }
                }

                // Initialize FullCalendar
                $('#calendar').fullCalendar({
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    editable: false,
                    selectable: true,
                    selectHelper: true,
                    defaultView: 'month',
                    events: filteredEvents,
                    height: 600,
                    
                    // When user selects a date range - FIXED
                    select: function(start, end) {
                        // Check if facility is selected
                        if (!selectedFacility) {
                            alert("Please select a facility first by clicking on one of the facility cards above.");
                            $('#calendar').fullCalendar('unselect');
                            return;
                        }
                        
                        // Clear previous form data
                        clearModalForm();
                        
                        // Set selected dates
                        var startDate = moment(start).format("YYYY-MM-DD");
                        var endDate = moment(end).format("YYYY-MM-DD");
                        
                        $("#event_start_date").val(startDate);
                        $("#event_end_date").val(endDate);
                        
                        // Set the selected facility in modal
                        $("#facility_name").val(selectedFacility);
                        $("#selected_facility").val(selectedFacility);
                        
                        // Display selected date in modal
                        $("#display_selected_date").text(moment(start).format("MMMM DD, YYYY"));
                        
                        // Show modal
                        $("#myModal").modal("show");
                        
                        // Unselect after opening modal
                        $('#calendar').fullCalendar('unselect');
                    },

                    // When user clicks on an existing event
                    eventClick: function(event) {
                        var eventDetails = "Event Details:\n\n";
                        eventDetails += "Facility: " + event.title + "\n";
                        eventDetails += "Date: " + moment(event.start).format("MMMM DD, YYYY") + "\n";
                        eventDetails += "Time: " + moment(event.start).format("h:mm A") + " - " + moment(event.end).format("h:mm A") + "\n";
                        eventDetails += "Status: " + event.status.charAt(0).toUpperCase() + event.status.slice(1) + "\n";
                        
                        alert(eventDetails);
                    },
                    
                    // Prevent selecting dates in the past
                    selectConstraint: {
                        start: moment().format('YYYY-MM-DD'),
                        end: '2100-01-01'
                    },
                    
                    // Styling
                    eventLimit: true,
                    timeFormat: 'h:mm A'
                });
            },
            error: function(xhr, status, error) {
                console.error("Error loading events:", xhr.responseText);
                alert("Error loading calendar events. Please refresh the page.");
                
                // Initialize empty calendar on error
                $('#calendar').fullCalendar({
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    selectable: true,
                    selectHelper: true,
                    defaultView: 'month',
                    height: 600,
                    
                    select: function(start, end) {
                        if (!selectedFacility) {
                            alert("Please select a facility first by clicking on one of the facility cards above.");
                            $('#calendar').fullCalendar('unselect');
                            return;
                        }
                        
                        clearModalForm();
                        
                        var startDate = moment(start).format("YYYY-MM-DD");
                        var endDate = moment(end).format("YYYY-MM-DD");
                        
                        $("#event_start_date").val(startDate);
                        $("#event_end_date").val(endDate);
                        $("#facility_name").val(selectedFacility);
                        $("#selected_facility").val(selectedFacility);
                        $("#display_selected_date").text(moment(start).format("MMMM DD, YYYY"));
                        
                        $("#myModal").modal("show");
                        $('#calendar').fullCalendar('unselect');
                    }
                });
            }
        });
    }

    /**
     * Save reservation to database - FIXED
     */
    function save_event() {
        // Get form values - use the stored facility value
        var facilityName = $("#selected_facility").val() || selectedFacility;
        var phone = $("#phone").val().trim();
        var startDate = $("#event_start_date").val();
        var endDate = $("#event_end_date").val();
        var timeStart = $("#selected_time_start").val();
        var timeEnd = $("#selected_time_end").val();
        var note = $("#event_note").val().trim();
        
        // Validate required fields
        if (!facilityName) {
            alert("Please select a facility from the cards above.");
            return;
        }
        
        if (!phone) {
            alert("Please enter your phone number.");
            $("#phone").focus();
            return;
        }
        
        if (!/^\d{10,11}$/.test(phone)) {
            alert("Please enter a valid phone number (10-11 digits).");
            $("#phone").focus();
            return;
        }
        
        if (!timeStart || !timeEnd) {
            alert("Please select a time slot.");
            return;
        }
        
        // Show loading state
        var $btn = $('#saveReservationBtn');
        var originalText = $btn.html();
        $btn.prop('disabled', true);
        $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        
        // Send AJAX request to save
        $.ajax({
            url: "save_event.php",
            type: "POST",
            dataType: "json",
            data: {
                facility_name: facilityName,
                phone: phone,
                event_start_date: startDate,
                event_end_date: endDate,
                time_start: timeStart,
                time_end: timeEnd,
                note: note
            },
            success: function(response) {
                // Reset button
                $btn.prop('disabled', false);
                $btn.html(originalText);
                
                if (response.status === true) {
                    alert(response.msg);
                    
                    // Close modal
                    $("#myModal").modal("hide");
                    
                    // Reload calendar to show new event
                    load_events();
                    
                    // Clear form BUT KEEP facility selected
                    clearModalForm();
                    // Keep selectedFacility and card selection active
                } else {
                    alert(response.msg || "Error saving reservation. Please try again.");
                }
            },
            error: function(xhr, status, error) {
                // Reset button
                $btn.prop('disabled', false);
                $btn.html(originalText);
                
                console.error("Error saving event:", xhr.responseText);
                
                var errorMsg = "Error saving reservation. Please try again.";
                
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.msg) {
                        errorMsg = errorResponse.msg;
                    }
                } catch (e) {
                    // Could not parse error response
                }
                
                alert(errorMsg);
            }
        });
    }

    /**
     * Clear modal form fields (but keep facility selection)
     */
    function clearModalForm() {
        // Don't clear facility_name or selected_facility - keep them!
        $('#phone').val('');
        $('#event_note').val('');
        $('#selected_time_start').val('');
        $('#selected_time_end').val('');
        
        // Reset time slot buttons
        $('.slot-btn').removeClass('selected');
        
        // Remove validation classes
        $('#phone').removeClass('is-invalid');
        $('#phoneFeedback').hide();
    }
    
    </script>
    
    <!-- Other JS -->
    <script src="javascript/sidebar.js"></script>
    <script src="javascript/progress-btn.js"></script>

</body>

</html>