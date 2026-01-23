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
var allEvents = []; // Store all events globally for time slot checking

// NEW: Store temporary modal data that gets discarded if not submitted
var tempModalData = {
    phone: '',
    note: '',
    timeStart: '',
    timeEnd: '',
    selectedDate: ''
};

$(document).ready(function () {
    // Initialize calendar when page loads
    load_events();

    // Facility card selection
    $('.card-link').on('click', function (e) {
        e.preventDefault();

        var clickedFacility = $(this).data('facility');
        
        // Check if user already has a complete reservation with a different facility
        if (isReservationComplete() && reservationData.facility !== clickedFacility) {
            Swal.fire({
                icon: "warning",
                title: "Change Facility?",
                html: "You already have a complete booking for <strong>" + reservationData.facility + "</strong>.<br>" +
                      "Changing the facility will clear your current reservation.<br><br>" +
                      "Do you want to change to <strong>" + clickedFacility + "</strong>?",
                showCancelButton: true,
                confirmButtonText: "Yes, Change Facility",
                cancelButtonText: "No, Keep Current",
                confirmButtonColor: "#ff6b6b"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Clear the complete reservation
                    tempCalendarEvent = null;
                    reservationData = {
                        facility: clickedFacility,
                        date: '',
                        timeStart: '',
                        timeEnd: '',
                        phone: '',
                        note: '',
                        totalCost: 0
                    };
                    
                    // Update UI
                    $('.col').removeClass('selected');
                    $(this).parent().addClass('selected');
                    selectedFacility = clickedFacility;
                    
                    // Update summary and reload calendar
                    updateSummaryDisplay();
                    load_events();
                    checkNextButtonState();
                }
                // If cancelled, do nothing - keep current facility selected
            });
            return;
        }

        // Normal facility selection (no complete reservation exists, or same facility clicked)
        // Remove selected class from all
        $('.col').removeClass('selected');

        // Add selected class to clicked facility
        $(this).parent().addClass('selected');

        // Store facility name globally
        selectedFacility = clickedFacility;

        // Update reservation data
        reservationData.facility = selectedFacility;

        // Update summary immediately
        updateSummaryDisplay();

        // Reload calendar to show only this facility's events
        load_events();
        
        // Check if we can enable next button
        checkNextButtonState();
    });

    // Time slot selection handler - MODIFIED to use temp data
    $(document).on('click', '.slot-btn', function (e) {
        // Prevent action if button is disabled or booked
        if ($(this).prop('disabled') || $(this).hasClass('disabled') || $(this).hasClass('booked')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Remove selected class from all buttons
        $('.slot-btn').removeClass('selected');

        // Add selected class to clicked button
        $(this).addClass('selected');

        // Store selected time in hidden fields
        $('#selected_time_start').val($(this).data('start'));
        $('#selected_time_end').val($(this).data('end'));

        // Store in TEMPORARY data (not reservationData yet)
        var timeStart = $(this).data('start');
        var timeEnd = $(this).data('end');
        tempModalData.timeStart = moment(timeStart, 'HH:mm').format('h:mm A');
        tempModalData.timeEnd = moment(timeEnd, 'HH:mm').format('h:mm A');
        
        // Check if form is complete
        checkModalFormCompletion();
    });

    // Phone number validation - MODIFIED for Philippine numbers
    $('#phone').on('input', function () {
        var value = $(this).val();

        // Remove non-numeric characters
        var cleaned = value.replace(/\D/g, '');
        $(this).val(cleaned);

        // Validate phone number
        validatePhoneNumber($(this), cleaned);

        // Store in TEMPORARY data (not reservationData yet)
        tempModalData.phone = cleaned;
        
        // Check if form is complete
        checkModalFormCompletion();
    });

    // Also validate on blur
    $('#phone').on('blur', function () {
        var cleaned = $(this).val();
        validatePhoneNumber($(this), cleaned);
    });

    // Note field - MODIFIED to use temp data
    $('#event_note').on('input', function () {
        tempModalData.note = $(this).val().trim();
    });

    // Save reservation button click handler - MODIFIED
    $('#saveReservationBtn').on('click', function () {
        saveToPaymentSection();
    });

    // NEW: Handle modal close button (X) - discard changes
    $('.btn-close, [data-bs-dismiss="modal"]').on('click', function() {
        if ($(this).closest('#myModal').length > 0) {
            discardModalChanges();
        }
    });

    // NEW: Handle clicking outside modal (backdrop) - discard changes
    $('#myModal').on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            discardModalChanges();
            $('#myModal').modal('hide');
        }
    });

    // NEW: Handle ESC key - discard changes
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#myModal').hasClass('show')) {
            discardModalChanges();
        }
    });

    // Modal shown event - check time slots after modal is fully loaded
    $('#myModal').on('shown.bs.modal', function () {
        var selectedDate = $("#event_start_date").val();
        var facilityName = $("#selected_facility").val() || selectedFacility;
        
        // Disable save button initially
        checkModalFormCompletion();
        
        if (selectedDate && facilityName) {
            // Small delay to ensure time slot buttons are rendered
            setTimeout(function() {
                checkAndDisableBookedSlots(selectedDate, facilityName);
            }, 100);
        }
    });

    // Modal hidden event - ensure temp data is cleared
    $('#myModal').on('hidden.bs.modal', function() {
        checkNextButtonState();
    });

    // Check form completion whenever inputs change
    $(document).on('change keyup', '#phone, #event_note', function() {
        checkModalFormCompletion();
    });

    // Check form completion when time slot is selected
    $(document).on('click', '.slot-btn', function() {
        // Delay to ensure the selection is processed
        setTimeout(function() {
            checkModalFormCompletion();
        }, 50);
    });

    // NEXT button click handler - validate before proceeding
    $('#next, .btn-next').on('click', function(e) {
        var currentPage = getCurrentPage();
        
        // Only validate when trying to proceed FROM the date-time page TO payment
        if (currentPage === 'date-time') {
            if (!isReservationComplete()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }
        
        // If on payment page, validate and save
        if (currentPage === 'payment') {
            e.preventDefault();
            saveFinalReservation();
            return false;
        }
        
        // For other pages (like select-facility), just check if Next button is enabled
        // If it's enabled, allow navigation
        if ($(this).prop('disabled')) {
            e.preventDefault();
            return false;
        }
        
        // Allow the default action (navigation) to proceed
        return true;
    });

    // Initialize next button state on page load with delay
    setTimeout(function() {
        checkNextButtonState();
    }, 500);
    
    // NEW: Continuously monitor and update button state (especially important for calendar page)
    setInterval(function() {
        checkNextButtonState();
    }, 500);
});

/**
 * Validate Philippine phone number
 */
function validatePhoneNumber($input, phone) {
    var $feedback = $('#phoneFeedback');
    
    // Empty check
    if (phone === '') {
        $input.removeClass('is-valid is-invalid');
        $feedback.hide();
        return false;
    }
    
    // Must be exactly 11 digits
    if (phone.length !== 11) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Phone number must be 11 digits').show();
        return false;
    }
    
    // Must start with 09
    if (!phone.startsWith('09')) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Phone number must start with 09').show();
        return false;
    }
    
    // Check for all same digits (e.g., 11111111111)
    if (/^(\d)\1{10}$/.test(phone)) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Please enter a valid phone number').show();
        return false;
    }
    
    // Valid
    $input.removeClass('is-invalid').addClass('is-valid');
    $feedback.hide();
    return true;
}

/**
 * NEW: Discard modal changes when closing without saving
 */
function discardModalChanges() {
    console.log("Modal closed without saving - discarding changes");
    
    // Clear temporary modal data
    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };
    
    // Clear the form inputs
    clearModalForm();
    
    // Re-check button state to ensure Next button is disabled
    checkNextButtonState();
}

/**
 * Open booking modal with selected date
 */
function openBookingModal(start, end) {
    clearModalForm();

    var startDate = moment(start).format("YYYY-MM-DD");
    var endDate = moment(end).format("YYYY-MM-DD");

    $("#event_start_date").val(startDate);
    $("#event_end_date").val(endDate);
    $("#facility_name").val(selectedFacility);
    $("#selected_facility").val(selectedFacility);
    $("#display_selected_date").text(moment(start).format("MMMM DD, YYYY"));

    // Store selected date in temp data (not in reservationData yet)
    tempModalData.selectedDate = moment(start).format("MMMM DD, YYYY");
    
    // DO NOT update reservationData.date here - wait for submit

    // Check and disable booked time slots AND past time slots for this date and facility
    checkAndDisableBookedSlots(startDate, selectedFacility);

    $("#myModal").modal("show");
    $('#calendar').fullCalendar('unselect');
}

/**
 * Load events from database and initialize calendar
 * FIXED: Filter out rejected reservations so those time slots become available again
 */
function load_events() {
    $.ajax({
        url: "display_event.php",
        dataType: "json",
        success: function (response) {
            console.log("Raw response from server:", response);
            
            // Store all events globally, but EXCLUDE rejected ones
            // This allows rejected time slots to be available for booking again
            var rawEvents = response.data || [];
            console.log("Total events from database:", rawEvents.length);
            
            allEvents = rawEvents.filter(function(event) {
                var isRejected = event.status === 'rejected';
                if (isRejected) {
                    console.log("Filtering out rejected event:", event);
                }
                return !isRejected;
            });
            
            console.log("Events after filtering rejected:", allEvents.length);

            // Destroy existing calendar instance
            $('#calendar').fullCalendar('destroy');

            // Filter events based on selected facility (still excluding rejected)
            var filteredEvents = [];
            if (allEvents.length > 0) {
                if (selectedFacility) {
                    filteredEvents = allEvents.filter(function (event) {
                        return event.title === selectedFacility;
                    });
                } else {
                    filteredEvents = allEvents;
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
                        Swal.fire({
                            icon: "warning",
                            title: "No Facility Selected",
                            text: "Please select a facility first by clicking on one of the facility cards.",
                            confirmButtonText: "OK"
                        });
                        $('#calendar').fullCalendar('unselect');
                        return;
                    }

                    // Check if user already has a saved booking
                    if (tempCalendarEvent !== null) {
                        Swal.fire({
                            icon: "warning",
                            title: "Booking Already Exists",
                            html: "You already have a booking for <strong>" + tempCalendarEvent.title + "</strong><br>" +
                                  "Date: " + moment(tempCalendarEvent.start).format("MMMM DD, YYYY") + "<br>" +
                                  "Time: " + moment(tempCalendarEvent.start).format("h:mm A") + " - " + moment(tempCalendarEvent.end).format("h:mm A") + "<br><br>" +
                                  "Do you want to change your booking?",
                            showCancelButton: true,
                            confirmButtonText: "Yes, Change Booking",
                            cancelButtonText: "No, Keep Current",
                            confirmButtonColor: "#ff6b6b"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Clear the temporary event to allow new booking
                                tempCalendarEvent = null;
                                
                                // Reset reservation data except facility
                                var savedFacility = reservationData.facility;
                                reservationData = {
                                    facility: savedFacility,
                                    date: '',
                                    timeStart: '',
                                    timeEnd: '',
                                    phone: '',
                                    note: '',
                                    totalCost: 0
                                };
                                
                                // Reload calendar without temp event
                                load_events();
                                
                                // Now open modal for new booking
                                openBookingModal(start, end);
                            } else {
                                $('#calendar').fullCalendar('unselect');
                            }
                        });
                        return;
                    }

                    openBookingModal(start, end);
                },

                eventClick: function (event) {
                    var eventDetails = "<strong>Facility:</strong> " + event.title + "<br>";
                    eventDetails += "<strong>Date:</strong> " + moment(event.start).format("MMMM DD, YYYY") + "<br>";
                    eventDetails += "<strong>Time:</strong> " + moment(event.start).format("h:mm A") + " - " + moment(event.end).format("h:mm A") + "<br>";
                    
                    if (event.status) {
                        eventDetails += "<strong>Status:</strong> " + event.status.charAt(0).toUpperCase() + event.status.slice(1);
                    } else if (event.isPending) {
                        eventDetails += "<strong>Status:</strong> Pending (Not yet submitted)";
                    }

                    Swal.fire({
                        title: "Event Details",
                        html: eventDetails,
                        icon: "info",
                        confirmButtonText: "OK"
                    });
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
            Swal.fire({
                icon: "error",
                title: "Error Loading Calendar",
                text: "Error loading calendar events. Please refresh the page.",
                confirmButtonText: "OK"
            });

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
                        Swal.fire({
                            icon: "warning",
                            title: "No Facility Selected",
                            text: "Please select a facility first by clicking on one of the facility cards above.",
                            confirmButtonText: "OK"
                        });
                        $('#calendar').fullCalendar('unselect');
                        return;
                    }

                    // Check if user already has a saved booking
                    if (tempCalendarEvent !== null) {
                        Swal.fire({
                            icon: "warning",
                            title: "Booking Already Exists",
                            html: "You already have a booking for <strong>" + tempCalendarEvent.title + "</strong><br>" +
                                  "Date: " + moment(tempCalendarEvent.start).format("MMMM DD, YYYY") + "<br>" +
                                  "Time: " + moment(tempCalendarEvent.start).format("h:mm A") + " - " + moment(tempCalendarEvent.end).format("h:mm A") + "<br><br>" +
                                  "Do you want to change your booking?",
                            showCancelButton: true,
                            confirmButtonText: "Yes, Change Booking",
                            cancelButtonText: "No, Keep Current",
                            confirmButtonColor: "#ff6b6b"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Clear the temporary event to allow new booking
                                tempCalendarEvent = null;
                                
                                // Reset reservation data except facility
                                var savedFacility = reservationData.facility;
                                reservationData = {
                                    facility: savedFacility,
                                    date: '',
                                    timeStart: '',
                                    timeEnd: '',
                                    phone: '',
                                    note: '',
                                    totalCost: 0
                                };
                                
                                // Reload calendar without temp event
                                load_events();
                                
                                // Now open modal for new booking
                                openBookingModal(start, end);
                            } else {
                                $('#calendar').fullCalendar('unselect');
                            }
                        });
                        return;
                    }

                    openBookingModal(start, end);
                }
            });
        }
    });
}

/**
 * Get current page/step
 */
function getCurrentPage() {
    // Check if calendar exists (Date & Time page)
    if ($('#calendar').length > 0 && $('#calendar').is(':visible')) {
        return 'date-time';
    }
    // Check for facility cards without calendar (Select Facility page)
    if ($('.card-link').length > 0 && $('#calendar').length === 0) {
        return 'select-facility';
    }
    // If there are facility cards and no visible calendar, it's select facility
    if ($('.card-link').length > 0 && !$('#calendar').is(':visible')) {
        return 'select-facility';
    }
    // Check for payment elements
    if ($('#uploadArea').length > 0 && $('#uploadArea').is(':visible')) {
        return 'payment';
    }
    
    // Fallback: check URL or active step indicator
    var activeStep = $('.progress-step.active, .step-circle.active').parent().find('.step-label').text().toLowerCase();
    if (activeStep.includes('facility')) {
        return 'select-facility';
    } else if (activeStep.includes('date') || activeStep.includes('time')) {
        return 'date-time';
    } else if (activeStep.includes('payment')) {
        return 'payment';
    }
    
    return 'unknown';
}

/**
 * Check if user can proceed from current page
 */
function canProceedFromCurrentPage() {
    var currentPage = getCurrentPage();
    
    if (currentPage === 'select-facility') {
        // On facility page, just need a facility selected
        return reservationData.facility !== null && reservationData.facility !== '';
    } else if (currentPage === 'date-time') {
        // On date & time page, need complete booking (must have submitted the modal)
        // Check that ALL required fields in reservationData are filled (not just facility)
        return isReservationComplete();
    } else if (currentPage === 'payment') {
        // On payment page, need complete reservation AND payment proof uploaded
        return isReservationComplete() && isPaymentProofUploaded();
    }
    
    // Default: allow if reservation is complete
    return isReservationComplete();
}

/**
 * Check if payment proof is uploaded
 */
function isPaymentProofUploaded() {
    if (typeof window.getUploadedPaymentProof === 'function') {
        var proof = window.getUploadedPaymentProof();
        return proof && proof.file !== null;
    }
    return false;
}

/**
 * Check if reservation is complete (all required data filled)
 */
function isReservationComplete() {
    return reservationData.facility && 
           reservationData.date && 
           reservationData.timeStart && 
           reservationData.timeEnd && 
           reservationData.phone &&
           reservationData.phone.length === 11 &&
           reservationData.phone.startsWith('09') &&
           !/^(\d)\1{10}$/.test(reservationData.phone);
}

/**
 * Check and update NEXT button state based on reservation completion
 */
function checkNextButtonState() {
    var $nextBtn = $('#next, .btn-next');
    
    // Don't do anything if button doesn't exist
    if ($nextBtn.length === 0) {
        return;
    }
    
    if (canProceedFromCurrentPage()) {
        $nextBtn.prop('disabled', false).removeClass('disabled');
    } else {
        $nextBtn.prop('disabled', true).addClass('disabled');
    }
}

/**
 * Check if modal form is complete and enable/disable save button - MODIFIED
 */
function checkModalFormCompletion() {
    var facilityName = $("#selected_facility").val() || selectedFacility;
    var phone = tempModalData.phone; // Use temp data instead
    var selectedDate = $("#event_start_date").val();
    var timeStart = $("#selected_time_start").val();
    var timeEnd = $("#selected_time_end").val();
    
    var isComplete = true;
    var missingFields = [];
    
    // Check facility
    if (!facilityName) {
        isComplete = false;
        missingFields.push("facility");
    }
    
    // Check date
    if (!selectedDate) {
        isComplete = false;
        missingFields.push("date");
    }
    
    // Check phone - must be 11 digits, start with 09, and not all same digits
    if (!phone || phone.length !== 11 || !phone.startsWith('09') || /^(\d)\1{10}$/.test(phone)) {
        isComplete = false;
        missingFields.push("phone");
    }
    
    // Check time slot
    if (!timeStart || !timeEnd) {
        isComplete = false;
        missingFields.push("time slot");
    }
    
    // Enable or disable the save button
    var $saveBtn = $('#saveReservationBtn');
    if (isComplete) {
        $saveBtn.prop('disabled', false).removeClass('disabled');
    } else {
        $saveBtn.prop('disabled', true).addClass('disabled');
    }
}

/**
 * Check and disable already booked time slots AND past time slots
 * FIXED: Only considers non-rejected bookings when checking for conflicts
 */
function checkAndDisableBookedSlots(selectedDate, facilityName) {
    console.log("Checking slots for date:", selectedDate, "facility:", facilityName);
    console.log("Total allEvents:", allEvents.length);
    
    // Get current date and time
    var now = moment();
    var currentDate = now.format("YYYY-MM-DD");
    var isToday = selectedDate === currentDate;

    // Get list of booked events, EXCLUDING rejected ones
    // This ensures rejected bookings don't block time slots
    // Filter allEvents to only include non-rejected bookings for this date and facility
    var bookedSlots = allEvents.filter(function(event) {
        // Skip if event has rejected status
        if (event.status === 'rejected') {
            console.log("Skipping rejected event in checkAndDisableBookedSlots:", event);
            return false;
        }
        
        var eventDate = moment(event.start).format("YYYY-MM-DD");
        var matches = event.title === facilityName && eventDate === selectedDate;
        
        if (matches) {
            console.log("Found matching booked slot:", event);
        }
        
        return matches;
    });
    
    console.log("Booked slots for this date/facility:", bookedSlots.length);

    // Also check if temp event matches
    if (tempCalendarEvent) {
        var tempEventDate = moment(tempCalendarEvent.start).format("YYYY-MM-DD");
        if (tempCalendarEvent.title === facilityName && tempEventDate === selectedDate) {
            bookedSlots.push(tempCalendarEvent);
        }
    }

    console.log("=== TIME SLOT CHECK DEBUG ===");
    console.log("Selected Date:", selectedDate);
    console.log("Facility:", facilityName);
    console.log("Total booked slots:", bookedSlots.length);
    bookedSlots.forEach(function(slot, index) {
        console.log("Booked Slot " + (index + 1) + ":", {
            title: slot.title,
            start: slot.start,
            end: slot.end,
            status: slot.status
        });
    });

    // Process each time slot button
    $('.slot-btn').each(function() {
        var $button = $(this);
        var slotStart = $button.data('start'); // e.g., "08:00"
        var slotEnd = $button.data('end');     // e.g., "09:00"
        
        if (!slotStart || !slotEnd) {
            return;
        }
        
        var slotStartTime = moment(selectedDate + ' ' + slotStart, 'YYYY-MM-DD HH:mm');
        var slotEndTime = moment(selectedDate + ' ' + slotEnd, 'YYYY-MM-DD HH:mm');
        
        // Check if this slot is in the past (only for today's date)
        var isPastTime = false;
        if (isToday) {
            // If the slot end time has already passed, disable it
            isPastTime = slotEndTime.isBefore(now) || slotEndTime.isSameOrBefore(now);
        }
        
        // Check if this slot conflicts with any booked event (excluding rejected)
        var isBooked = bookedSlots.some(function(event) {
            var eventStart = moment(event.start);
            var eventEnd = moment(event.end);
            
            // Check for time overlap
            var overlaps = (slotStartTime.isBefore(eventEnd) && slotEndTime.isAfter(eventStart));
            
            return overlaps;
        });
        
        if (isBooked) {
            // Disable the booked slot
            $button.prop('disabled', true)
                   .addClass('disabled booked')
                   .removeClass('selected') // Remove selected if it was selected
                   .css({
                       'opacity': '0.5',
                       'cursor': 'not-allowed',
                       'background-color': '#e0e0e0',
                       'color': '#999',
                       'pointer-events': 'none'
                   });
            
            // Add "Booked" badge if not already present
            if ($button.find('.badge-danger').length === 0) {
                $button.append(' <span class="badge badge-danger ml-2">Booked</span>');
            }
        } else if (isPastTime) {
            // Disable past time slots
            $button.prop('disabled', true)
                   .addClass('disabled past-time')
                   .removeClass('selected') // Remove selected if it was selected
                   .css({
                       'opacity': '0.4',
                       'cursor': 'not-allowed',
                       'background-color': '#f5f5f5',
                       'color': '#aaa',
                       'pointer-events': 'none'
                   });
            
            // Add "Past" badge if not already present
            if ($button.find('.badge-secondary').length === 0) {
                $button.append(' <span class="badge badge-secondary ml-2">Past</span>');
            }
            
            // Remove any "Booked" badge
            $button.find('.badge-danger').remove();
        } else {
            // Enable the available slot (remove any previous disabled state)
            $button.prop('disabled', false)
                   .removeClass('disabled booked past-time')
                   .css({
                       'opacity': '',
                       'cursor': '',
                       'background-color': '',
                       'color': '',
                       'pointer-events': ''
                   });
            
            // Remove both "Booked" and "Past" badges
            $button.find('.badge-danger, .badge-secondary').remove();
        }
    });
}

/**
 * Save reservation data to payment section - MODIFIED to save from temp data
 */
function saveToPaymentSection() {
    // Get form values from temp data
    var facilityName = $("#selected_facility").val() || selectedFacility;
    var phone = tempModalData.phone;
    var startDate = $("#event_start_date").val();
    var endDate = $("#event_end_date").val();
    var timeStart = $("#selected_time_start").val();
    var timeEnd = $("#selected_time_end").val();
    var note = tempModalData.note;

    // Validate required fields
    if (!facilityName) {
        Swal.fire({
            icon: "warning",
            title: "Facility Required",
            text: "Please select a facility from the cards."
        });
        return;
    }

    if (!phone) {
        Swal.fire({
            icon: "warning",
            title: "Phone Required",
            text: "Please enter your phone number."
        }).then(() => {
            $("#phone").focus();
        });
        return;
    }

    // Validate Philippine phone number format
    if (phone.length !== 11) {
        Swal.fire({
            icon: "error",
            title: "Invalid Phone Number",
            text: "Phone number must be exactly 11 digits."
        }).then(() => {
            $("#phone").focus();
        });
        return;
    }

    if (!phone.startsWith('09')) {
        Swal.fire({
            icon: "error",
            title: "Invalid Phone Number",
            text: "Phone number must start with 09."
        }).then(() => {
            $("#phone").focus();
        });
        return;
    }

    // Check for repeated digits
    if (/^(\d)\1{10}$/.test(phone)) {
        Swal.fire({
            icon: "error",
            title: "Invalid Phone Number",
            text: "Please enter a valid Philippine mobile number."
        }).then(() => {
            $("#phone").focus();
        });
        return;
    }

    if (!timeStart || !timeEnd) {
        Swal.fire({
            icon: "warning",
            title: "Time Slot Required",
            text: "Please select a time slot."
        });
        return;
    }

    // NOW SAVE TO ACTUAL reservationData (only when Submit is clicked)
    reservationData.date = moment(startDate).format("MMMM DD, YYYY");
    reservationData.timeStart = moment(timeStart, 'HH:mm').format('h:mm A');
    reservationData.timeEnd = moment(timeEnd, 'HH:mm').format('h:mm A');
    reservationData.phone = phone;
    reservationData.note = note;

    // Calculate and save cost
    calculateAndUpdateCost();

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

    // Enable next button since reservation is now complete
    checkNextButtonState();

    // Clear temp modal data since it's now saved
    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };

    // Show success message
    Swal.fire({
        icon: "success",
        title: "Reservation Saved!",
        text: "Your booking details have been saved. You can now proceed to the payment step.",
        confirmButtonText: "OK"
    });

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
            breakdownText = 'Select time and date';
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
        'Basketball Court': 100,
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
}

/**
 * Clear modal form fields (but keep facility selection) - MODIFIED
 */
function clearModalForm() {
    // Clear form inputs
    $('#phone').val('');
    $('#event_note').val('');
    $('#selected_time_start').val('');
    $('#selected_time_end').val('');

    // Reset time slot buttons - only remove selected class, don't disable
    $('.slot-btn').removeClass('selected');

    // Remove validation classes
    $('#phone').removeClass('is-invalid is-valid');
    $('#phoneFeedback').hide();
    
    // Clear temporary modal data
    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };
    
    // Disable save button since form is now incomplete
    $('#saveReservationBtn').prop('disabled', true).addClass('disabled');
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
        Swal.fire({
            icon: "warning",
            title: "Incomplete Reservation",
            text: "Please complete the reservation details first by selecting a facility, date, and time.",
            confirmButtonText: "OK"
        });
        return false;
    }

    if (!reservationData.phone) {
        Swal.fire({
            icon: "warning",
            title: "Phone Number Required",
            text: "Phone number is required. Please go back and enter your phone number.",
            confirmButtonText: "OK"
        });
        return false;
    }

    // Validate payment proof - MUST have uploaded file
    if (typeof window.validatePaymentProof !== 'function' || !window.validatePaymentProof()) {
        return false;
    }

    // Get the uploaded payment proof file
    var paymentProof = window.getUploadedPaymentProof();
    
    if (!paymentProof || !paymentProof.file) {
        Swal.fire({
            icon: "warning",
            title: "Payment Proof Required",
            text: "Please upload your payment proof screenshot before proceeding.",
            confirmButtonText: "OK"
        });
        return false;
    }

    // Show loading state
    var $btn = $('#next');
    var originalText = $btn.html();
    $btn.prop('disabled', true);
    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

    // Prepare data for database
    var startDate = moment(reservationData.date, 'MMMM DD, YYYY').format('YYYY-MM-DD');
    var timeStart = moment(reservationData.timeStart, 'h:mm A').format('HH:mm');
    var timeEnd = moment(reservationData.timeEnd, 'h:mm A').format('HH:mm');

    // Create FormData for file upload
    var formData = new FormData();
    formData.append('facility_name', reservationData.facility);
    formData.append('phone', reservationData.phone);
    formData.append('event_start_date', startDate);
    formData.append('event_end_date', startDate); // Same day
    formData.append('time_start', timeStart);
    formData.append('time_end', timeEnd);
    formData.append('note', reservationData.note || '');
    formData.append('payment_proof', paymentProof.file); // Add the uploaded file

    // Send AJAX request to save with file
    $.ajax({
        url: "save_event.php",
        type: "POST",
        data: formData,
        processData: false,  // Important for file upload
        contentType: false,  // Important for file upload
        dataType: "json",
        success: function (response) {
            // Reset button
            $btn.prop('disabled', false);
            $btn.html(originalText);

            if (response.status === true) {
                Swal.fire({
                    icon: "success",
                    title: "Reservation Saved!",
                    text: "Your reservation has been saved successfully.",
                    confirmButtonText: "OK"
                }).then(() => {
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

                    // Redirect after alert closes
                    window.location.href = "../my-reservations/myreservations.php";
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Reservation Failed",
                    text: response.msg || "Error saving reservation. Please try again."
                });
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

            Swal.fire({
                icon: "error",
                title: "Save Failed",
                text: errorMsg,
                confirmButtonText: "OK"
            });
        }
    });

    return false;
}