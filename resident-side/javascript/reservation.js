// Global variables
var selectedFacility = null;
var reservationData = {
    facility: '',
    date: '',
    timeStart: '',
    timeEnd: '',
    phone: '',
    note: '',
    totalCost: 0
};
var tempCalendarEvent = null; // Store temporary event for calendar display

$(document).ready(function () {
    // Initialize calendar when page loads
    load_events();

    // Facility card selection
    $('.card-link').on('click', function (e) {
        e.preventDefault();

        // Remove selected class from all
        $('.col').removeClass('selected');

        // Add selected class to clicked facility
        $(this).parent().addClass('selected');

        // Store facility name globally
        selectedFacility = $(this).data('facility');

        console.log("Facility selected:", selectedFacility);

        // Update reservation data
        reservationData.facility = selectedFacility;

        // Update summary immediately
        updateSummaryDisplay();

        // Reload calendar to show only this facility's events
        load_events();
    });

    // Time slot selection handler
    $(document).on('click', '.slot-btn', function () {
        // Remove selected class from all buttons
        $('.slot-btn').removeClass('selected');

        // Add selected class to clicked button
        $(this).addClass('selected');

        // Store selected time in hidden fields
        $('#selected_time_start').val($(this).data('start'));
        $('#selected_time_end').val($(this).data('end'));

        // Update reservation data with time
        var timeStart = $(this).data('start');
        var timeEnd = $(this).data('end');
        reservationData.timeStart = moment(timeStart, 'HH:mm').format('h:mm A');
        reservationData.timeEnd = moment(timeEnd, 'HH:mm').format('h:mm A');

        // Calculate cost
        calculateAndUpdateCost();

        // Update summary display
        updateSummaryDisplay();
    });

    // Phone number validation (only numbers)
    $('#phone').on('input', function () {
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

        // Update reservation data
        reservationData.phone = cleaned;
    });

    // Save reservation button click handler - MODIFIED
    $('#saveReservationBtn').on('click', function () {
        saveToPaymentSection();
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
                    filteredEvents = response.data.filter(function (event) {
                        return event.title === selectedFacility;
                    });
                } else {
                    filteredEvents = response.data;
                }
            }

            // Add temporary event if it exists and matches selected facility
            if (tempCalendarEvent) {
                if (!selectedFacility || tempCalendarEvent.title === selectedFacility) {
                    filteredEvents.push(tempCalendarEvent);
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

                select: function (start, end) {
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

                    // Update reservation data with selected date
                    reservationData.date = moment(start).format("MMMM DD, YYYY");
                    
                    // Update summary display
                    updateSummaryDisplay();

                    $("#myModal").modal("show");
                    $('#calendar').fullCalendar('unselect');
                },

                eventClick: function (event) {
                    var eventDetails = "Event Details:\n\n";
                    eventDetails += "Facility: " + event.title + "\n";
                    eventDetails += "Date: " + moment(event.start).format("MMMM DD, YYYY") + "\n";
                    eventDetails += "Time: " + moment(event.start).format("h:mm A") + " - " + moment(event.end).format("h:mm A") + "\n";
                    
                    if (event.status) {
                        eventDetails += "Status: " + event.status.charAt(0).toUpperCase() + event.status.slice(1) + "\n";
                    } else if (event.isPending) {
                        eventDetails += "Status: Pending (Not yet submitted)\n";
                    }

                    alert(eventDetails);
                },

                selectConstraint: {
                    start: moment().format('YYYY-MM-DD'),
                    end: '2100-01-01'
                },

                eventLimit: true,
                timeFormat: 'h:mm A'
            });
        },
        error: function (xhr, status, error) {
            console.error("Error loading events:", xhr.responseText);
            alert("Error loading calendar events. Please refresh the page.");

            // Initialize empty calendar on error but still include temp event
            var events = [];
            if (tempCalendarEvent) {
                if (!selectedFacility || tempCalendarEvent.title === selectedFacility) {
                    events.push(tempCalendarEvent);
                }
            }

            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                selectable: true,
                selectHelper: true,
                defaultView: 'month',
                events: events,
                height: 600,

                select: function (start, end) {
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

                    // Update reservation data with selected date
                    reservationData.date = moment(start).format("MMMM DD, YYYY");
                    
                    // Update summary display
                    updateSummaryDisplay();

                    $("#myModal").modal("show");
                    $('#calendar').fullCalendar('unselect');
                }
            });
        }
    });
}

/**
 * Save reservation data to payment section - NEW FUNCTION
 */
function saveToPaymentSection() {
    // Get form values
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

    // Store note (if any)
    reservationData.note = note;

    // Make sure all data is up to date
    if (!reservationData.date) {
        reservationData.date = moment(startDate).format("MMMM DD, YYYY");
    }
    if (!reservationData.timeStart || !reservationData.timeEnd) {
        reservationData.timeStart = moment(timeStart, 'HH:mm').format('h:mm A');
        reservationData.timeEnd = moment(timeEnd, 'HH:mm').format('h:mm A');
    }
    if (!reservationData.totalCost || reservationData.totalCost === 0) {
        calculateAndUpdateCost();
    }

    // Create temporary calendar event
    var eventStartDateTime = moment(startDate + ' ' + timeStart, 'YYYY-MM-DD HH:mm');
    var eventEndDateTime = moment(startDate + ' ' + timeEnd, 'YYYY-MM-DD HH:mm');

    tempCalendarEvent = {
        title: facilityName,
        start: eventStartDateTime.format('YYYY-MM-DD HH:mm:ss'),
        end: eventEndDateTime.format('YYYY-MM-DD HH:mm:ss'),
        color: '#ffc107', // Yellow color to indicate pending/temporary
        textColor: '#000',
        isPending: true // Custom flag to identify temporary events
    };

    // Final update to summary
    updateSummaryDisplay();

    // Reload calendar to show the temporary event
    load_events();

    // Show success message
    alert("Reservation details saved! You can now proceed to the next step when ready.");

    // Close modal
    $("#myModal").modal("hide");

    // Clear form but keep facility selected
    clearModalForm();
}

/**
 * Update summary display in real-time as user selects options
 */
function updateSummaryDisplay() {
    // Update all summary cards (both in date/time section and payment section)
    $('.summary-card').each(function() {
        var $summaryCard = $(this);
        
        // Update facility name
        if (reservationData.facility) {
            $summaryCard.find('.facility-name').text(reservationData.facility);
        } else {
            $summaryCard.find('.facility-name').text('No facility selected');
        }
        
        // Update date and time
        var datetimeText = '';
        if (reservationData.date) {
            datetimeText = reservationData.date;
            if (reservationData.timeStart && reservationData.timeEnd) {
                datetimeText += ', ' + reservationData.timeStart + ' - ' + reservationData.timeEnd;
            }
        } else {
            datetimeText = 'No date selected';
        }
        $summaryCard.find('.reservation-datetime').text(datetimeText);
        
        // Update cost breakdown
        var breakdownText = '';
        if (reservationData.facility && reservationData.date) {
            breakdownText = reservationData.facility + ' Reservation - ' + reservationData.date;
            if (reservationData.timeStart && reservationData.timeEnd) {
                breakdownText += ', ' + reservationData.timeStart + ' - ' + reservationData.timeEnd;
            }
        } else {
            breakdownText = 'Select facility and date';
        }
        $summaryCard.find('.breakdown-item').html(breakdownText);
        
        // Update price
        var formattedPrice = '₱0.00';
        if (reservationData.totalCost > 0) {
            formattedPrice = '₱' + reservationData.totalCost.toFixed(2);
        }
        $summaryCard.find('.breakdown-price').text(formattedPrice);
        $summaryCard.find('.total-price').text(formattedPrice);
    });
}

/**
 * Calculate and update cost based on current reservation data
 */
function calculateAndUpdateCost() {
    if (!reservationData.facility || !reservationData.timeStart || !reservationData.timeEnd) {
        reservationData.totalCost = 0;
        return;
    }

    // Calculate cost based on facility
    var costPerHour = 350; // Default cost per hour
    var facilityPrices = {
        'Chapel': 500,
        'Basketball Court': 350,
        'Multipurpose Hall': 600,
        'Tennis Court': 400
    };

    if (facilityPrices[reservationData.facility]) {
        costPerHour = facilityPrices[reservationData.facility];
    }

    // Calculate total hours
    var start = moment(reservationData.timeStart, 'h:mm A');
    var end = moment(reservationData.timeEnd, 'h:mm A');
    var hours = end.diff(start, 'hours', true);
    reservationData.totalCost = costPerHour * hours;
}

/**
 * Update the payment summary section with reservation data
 */
function updatePaymentSummary() {
    // Update all summary cards (both in date/time section and payment section)
    $('.summary-card').each(function() {
        var $summaryCard = $(this);
        
        // Update facility name
        $summaryCard.find('.facility-name').text(reservationData.facility);
        
        // Update date and time
        var datetimeText = reservationData.date + ', ' + 
                          reservationData.timeStart + ' - ' + 
                          reservationData.timeEnd;
        $summaryCard.find('.reservation-datetime').text(datetimeText);
        
        // Update cost breakdown
        var breakdownText = reservationData.facility + ' Reservation - ' + 
                           reservationData.date + ', ' + 
                           reservationData.timeStart + ' - ' + 
                           reservationData.timeEnd;
        $summaryCard.find('.breakdown-item').html(breakdownText);
        
        // Update price
        var formattedPrice = '₱' + reservationData.totalCost.toFixed(2);
        $summaryCard.find('.breakdown-price').text(formattedPrice);
        $summaryCard.find('.total-price').text(formattedPrice);
    });

    console.log("Payment summary updated:", reservationData);
}

/**
 * Clear modal form fields (but keep facility selection)
 */
function clearModalForm() {
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

/**
 * Function to get reservation data (for when you actually submit to database)
 */
function getReservationData() {
    return reservationData;
}

/**
 * Save final reservation to database (called from payment page)
 */
function saveFinalReservation() {
    // Validate that reservation data exists
    if (!reservationData.facility || !reservationData.date || !reservationData.timeStart || !reservationData.timeEnd) {
        alert("Please complete the reservation details first by selecting a facility, date, and time.");
        return false;
    }

    if (!reservationData.phone) {
        alert("Phone number is required. Please go back and enter your phone number.");
        return false;
    }

    // TODO: Add payment proof validation here when you implement file upload
    // For now, we'll proceed without it

    // Show loading state
    var $btn = $('#next');
    var originalText = $btn.html();
    $btn.prop('disabled', true);
    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

    // Prepare data for database
    var startDate = moment(reservationData.date, 'MMMM DD, YYYY').format('YYYY-MM-DD');
    var timeStart = moment(reservationData.timeStart, 'h:mm A').format('HH:mm');
    var timeEnd = moment(reservationData.timeEnd, 'h:mm A').format('HH:mm');

    // Send AJAX request to save
    $.ajax({
        url: "save_event.php",
        type: "POST",
        dataType: "json",
        data: {
            facility_name: reservationData.facility,
            phone: reservationData.phone,
            event_start_date: startDate,
            event_end_date: startDate, // Same day
            time_start: timeStart,
            time_end: timeEnd,
            note: reservationData.note || ''
        },
        success: function (response) {
            // Reset button
            $btn.prop('disabled', false);
            $btn.html(originalText);

            if (response.status === true) {
                alert("Reservation saved successfully! " + (response.msg || ""));

                // Clear temporary calendar event
                tempCalendarEvent = null;

                // Clear reservation data
                reservationData = {
                    facility: '',
                    date: '',
                    timeStart: '',
                    timeEnd: '',
                    phone: '',
                    note: '',
                    totalCost: 0
                };

                // Redirect to My Reservations page or refresh
                window.location.href = "../my-reservations/myreservations.php";
            } else {
                alert(response.msg || "Error saving reservation. Please try again.");
            }
        },
        error: function (xhr, status, error) {
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

    return false;
}