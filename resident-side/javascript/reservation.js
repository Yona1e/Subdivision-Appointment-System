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
var rangeStartSlot = null; // Track start of range selection

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
            });
            return;
        }

        // Normal facility selection
        $('.col').removeClass('selected');
        $(this).parent().addClass('selected');
        selectedFacility = clickedFacility;
        reservationData.facility = selectedFacility;
        updateSummaryDisplay();
        load_events();
        checkNextButtonState();
    });

    // Time slot selection handler (Range Selection)
    $(document).on('click', '.slot-btn', function (e) {
        if ($(this).prop('disabled') || $(this).hasClass('disabled') || $(this).hasClass('booked')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        var $clickedSlot = $(this);
        var clickedStart = $clickedSlot.data('start');
        var clickedEnd = $clickedSlot.data('end');

        // Logic: First click sets start. Second click sets end (if valid).
        // If clicking earlier than start, or if start not set, it becomes new start.

        if (!rangeStartSlot) {
            // Case 1: Start new range
            rangeStartSlot = $clickedSlot;
            $('.slot-btn').removeClass('selected');
            $clickedSlot.addClass('selected');

            // Update data for single slot
            updateTimeSelection(clickedStart, clickedEnd);
        } else {
            var startDisplay = rangeStartSlot.data('start'); // e.g. "09:00"

            // Compare times (string comparison works for ISO 24h format "HH:mm")
            if (clickedStart < startDisplay) {
                // Case 2: Clicked earlier -> New Start
                rangeStartSlot = $clickedSlot;
                $('.slot-btn').removeClass('selected');
                $clickedSlot.addClass('selected');
                updateTimeSelection(clickedStart, clickedEnd);
            } else if (clickedStart === startDisplay) {
                // Case 3: Clicked same slot -> Keep as single slot (or toggle off? Keeping as selection)
                // Just keep it selected.
                updateTimeSelection(clickedStart, clickedEnd);
                // If user meant to just select one, clicking again resets range start so next click can be anything?
                // Let's keep rangeStartSlot set so next click AFTER this one makes a range.
            } else {
                // Case 4: Clicked later -> Range End
                var $allSlots = $('.slot-btn');
                var startIdx = $allSlots.index(rangeStartSlot);
                var endIdx = $allSlots.index($clickedSlot);

                var isValidRange = true;
                var $rangeSlots = $allSlots.slice(startIdx, endIdx + 1);

                // Validate range availability
                $rangeSlots.each(function () {
                    if ($(this).hasClass('disabled') || $(this).hasClass('booked') || $(this).prop('disabled')) {
                        isValidRange = false;
                        return false;
                    }
                });

                if (!isValidRange) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Selection',
                        text: 'Selected range contains booked or unavailable slots.'
                    });
                    // Reset to just the clicked slot? Or keep start?
                    // Let's reset to just this new clicked slot to be safe/clear
                    rangeStartSlot = $clickedSlot;
                    $('.slot-btn').removeClass('selected');
                    $clickedSlot.addClass('selected');
                    updateTimeSelection(clickedStart, clickedEnd);
                } else {
                    // Valid Range
                    $('.slot-btn').removeClass('selected');
                    $rangeSlots.addClass('selected');

                    // Update selection with Range START and Range END
                    // Start of first slot, End of last slot

                    // We already have clickedStart (start of last slot) and clickedEnd (end of last slot)
                    // We need Start of first slot.
                    var finalStartTime = rangeStartSlot.data('start');
                    var finalEndTime = clickedEnd; // End of the clicked slot

                    updateTimeSelection(finalStartTime, finalEndTime);

                    // Reset range start so next click starts a NEW selection
                    rangeStartSlot = null;
                }
            }
        }
    });

    function updateTimeSelection(start, end) {
        $('#selected_time_start').val(start);
        $('#selected_time_end').val(end);

        tempModalData.timeStart = moment(start, 'HH:mm').format('h:mm A');
        tempModalData.timeEnd = moment(end, 'HH:mm').format('h:mm A');

        checkModalFormCompletion();
    }

    // Phone number validation for Philippine numbers
    $('#phone').on('input', function () {
        var value = $(this).val();
        var cleaned = value.replace(/\D/g, '');
        $(this).val(cleaned);
        validatePhoneNumber($(this), cleaned);
        tempModalData.phone = cleaned;
        checkModalFormCompletion();
    });

    $('#phone').on('blur', function () {
        validatePhoneNumber($(this), $(this).val());
    });

    // Note field
    $('#event_note').on('input', function () {
        tempModalData.note = $(this).val().trim();
    });

    // Save reservation button
    $('#saveReservationBtn').on('click', function () {
        saveToPaymentSection();
    });

    // Handle modal close - discard changes
    $('.btn-close, [data-bs-dismiss="modal"]').on('click', function () {
        if ($(this).closest('#myModal').length > 0) {
            discardModalChanges();
        }
    });

    // Handle clicking outside modal
    $('#myModal').on('click', function (e) {
        if ($(e.target).hasClass('modal')) {
            discardModalChanges();
            $('#myModal').modal('hide');
        }
    });

    // Handle ESC key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#myModal').hasClass('show')) {
            discardModalChanges();
        }
    });

    // Modal shown event
    $('#myModal').on('shown.bs.modal', function () {
        var selectedDate = $("#event_start_date").val();
        var facilityName = $("#selected_facility").val() || selectedFacility;

        checkModalFormCompletion();

        if (selectedDate && facilityName) {
            setTimeout(function () {
                checkAndDisableBookedSlots(selectedDate, facilityName);
            }, 100);
        }
    });

    // Modal hidden event
    $('#myModal').on('hidden.bs.modal', function () {
        checkNextButtonState();
    });

    // Check form completion on input changes
    $(document).on('change keyup', '#phone, #event_note', function () {
        checkModalFormCompletion();
    });

    $(document).on('click', '.slot-btn', function () {
        setTimeout(function () {
            checkModalFormCompletion();
        }, 50);
    });

    // NEXT button click handler
    $('#next, .btn-next').on('click', function (e) {
        var currentPage = getCurrentPage();

        if (currentPage === 'date-time') {
            if (!isReservationComplete()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }

        if (currentPage === 'payment') {
            e.preventDefault();
            saveFinalReservation();
            return false;
        }

        if ($(this).prop('disabled')) {
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Initialize next button state
    setTimeout(function () {
        checkNextButtonState();
    }, 500);

    setInterval(function () {
        checkNextButtonState();
    }, 500);
});

/**
 * Validate Philippine phone number
 */
function validatePhoneNumber($input, phone) {
    var $feedback = $('#phoneFeedback');

    if (phone === '') {
        $input.removeClass('is-valid is-invalid');
        $feedback.hide();
        return false;
    }

    if (phone.length !== 11) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Phone number must be 11 digits').show();
        return false;
    }

    if (!phone.startsWith('09')) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Phone number must start with 09').show();
        return false;
    }

    if (/^(\d)\1{10}$/.test(phone)) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $feedback.text('Please enter a valid phone number').show();
        return false;
    }

    $input.removeClass('is-invalid').addClass('is-valid');
    $feedback.hide();
    return true;
}

/**
 * Discard modal changes when closing without saving
 */
function discardModalChanges() {
    console.log("Modal closed without saving - discarding changes");

    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };
    rangeStartSlot = null; // Reset range selection logic

    clearModalForm();
    checkNextButtonState();
}

/**
 * Open booking modal with selected date
 */
function openBookingModal(start, end) {
    clearModalForm();
    rangeStartSlot = null; // Reset range selection logic

    var startDate = moment(start).format("YYYY-MM-DD");
    var endDate = moment(end).format("YYYY-MM-DD");

    $("#event_start_date").val(startDate);
    $("#event_end_date").val(endDate);
    $("#facility_name").val(selectedFacility);
    $("#selected_facility").val(selectedFacility);
    $("#display_selected_date").text(moment(start).format("MMMM DD, YYYY"));

    tempModalData.selectedDate = moment(start).format("MMMM DD, YYYY");

    checkAndDisableBookedSlots(startDate, selectedFacility);

    $("#myModal").modal("show");
    $('#calendar').fullCalendar('unselect');
}

/**
 * Load events from database and initialize calendar
 * FIXED: Filter out rejected reservations so time slots become available
 */
function load_events() {
    $.ajax({
        url: "display_event.php",
        dataType: "json",
        success: function (response) {
            console.log("Raw response from server:", response);

            // CRITICAL: Filter out rejected and cancelled reservations
            // Only pending, approved, and completed reservations should block time slots
            var rawEvents = response.data || [];
            console.log("Total events from database:", rawEvents.length);

            allEvents = rawEvents.filter(function (event) {
                var shouldExclude = event.status === 'rejected' || event.status === 'cancelled';
                if (shouldExclude) {
                    console.log("Filtering out " + event.status + " event:", event);
                }
                return !shouldExclude;
            });

            console.log("Events after filtering rejected/cancelled:", allEvents.length);

            $('#calendar').fullCalendar('destroy');

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

            if (tempCalendarEvent) {
                if (!selectedFacility || tempCalendarEvent.title === selectedFacility) {
                    filteredEvents.push(tempCalendarEvent);
                }
            }

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
                    // Check for past dates FIRST
                    var today = moment().startOf('day');
                    if (start.isBefore(today)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Cannot book past dates',
                            confirmButtonColor: '#3b82f6'
                        });
                        $('#calendar').fullCalendar('unselect');
                        return;
                    }

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
                                tempCalendarEvent = null;

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

                                load_events();
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
                    var statusText = event.status ? event.status.charAt(0).toUpperCase() + event.status.slice(1) :
                        (event.isPending ? "Pending (Not yet submitted)" : "N/A");

                    // FIXED: Access user_role from event object
                    var userRole = event.user_role || "Unknown";

                    console.log("Event clicked:", event); // Debug
                    console.log("User role:", userRole); // Debug

                    Swal.fire({
                        title: "Event Details",
                        icon: "info",
                        confirmButtonText: "OK",
                        html: `
            <div style="text-align: left; padding: 10px;">
                <p style="margin: 10px 0;"><strong>Facility:</strong> ${event.title}</p>
                <p style="margin: 10px 0;"><strong>Date:</strong> ${moment(event.start).format("MMMM DD, YYYY")}</p>
                <p style="margin: 10px 0;"><strong>Time:</strong> ${moment(event.start).format("h:mm A")} - ${moment(event.end).format("h:mm A")}</p>
                <p style="margin: 10px 0;"><strong>Status:</strong> ${statusText}</p>
                <p style="margin: 10px 0;"><strong>User Type:</strong> ${userRole}</p>
            </div>
        `
                    });
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
                            text: "Please select a facility first.",
                            confirmButtonText: "OK"
                        });
                        $('#calendar').fullCalendar('unselect');
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
    if ($('#calendar').length > 0 && $('#calendar').is(':visible')) {
        return 'date-time';
    }
    if ($('.card-link').length > 0 && $('#calendar').length === 0) {
        return 'select-facility';
    }
    if ($('.card-link').length > 0 && !$('#calendar').is(':visible')) {
        return 'select-facility';
    }
    if ($('#uploadArea').length > 0 && $('#uploadArea').is(':visible')) {
        return 'payment';
    }

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
        return reservationData.facility !== null && reservationData.facility !== '';
    } else if (currentPage === 'date-time') {
        return isReservationComplete();
    } else if (currentPage === 'payment') {
        return isReservationComplete() && isPaymentProofUploaded();
    }

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
 * Check if reservation is complete
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
 * Check and update NEXT button state
 */
function checkNextButtonState() {
    var $nextBtn = $('#next, .btn-next');

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
 * Check if modal form is complete
 */
function checkModalFormCompletion() {
    var facilityName = $("#selected_facility").val() || selectedFacility;
    var phone = tempModalData.phone;
    var selectedDate = $("#event_start_date").val();
    var timeStart = $("#selected_time_start").val();
    var timeEnd = $("#selected_time_end").val();

    var isComplete = true;

    if (!facilityName || !selectedDate || !timeStart || !timeEnd) {
        isComplete = false;
    }

    if (!phone || phone.length !== 11 || !phone.startsWith('09') || /^(\d)\1{10}$/.test(phone)) {
        isComplete = false;
    }

    var $saveBtn = $('#saveReservationBtn');
    if (isComplete) {
        $saveBtn.prop('disabled', false).removeClass('disabled');
    } else {
        $saveBtn.prop('disabled', true).addClass('disabled');
    }
}

/**
 * Check and disable booked/past time slots
 * CRITICAL: Only considers pending, approved, and completed reservations
 * Rejected and cancelled reservations DO NOT block time slots
 */
function checkAndDisableBookedSlots(selectedDate, facilityName) {
    console.log("Checking slots for date:", selectedDate, "facility:", facilityName);
    console.log("Total allEvents:", allEvents.length);

    var now = moment();
    var currentDate = now.format("YYYY-MM-DD");
    var isToday = selectedDate === currentDate;

    // CRITICAL: Only include non-rejected, non-cancelled bookings
    // This ensures rejected/cancelled reservations don't block time slots
    var bookedSlots = allEvents.filter(function (event) {
        if (event.status === 'rejected' || event.status === 'cancelled') {
            console.log("Skipping " + event.status + " event:", event);
            return false;
        }

        var eventDate = moment(event.start).format("YYYY-MM-DD");
        var matches = event.title === facilityName && eventDate === selectedDate;

        if (matches) {
            console.log("Found matching booked slot:", event);
        }

        return matches;
    });

    console.log("Booked slots for this date/facility (excluding rejected/cancelled):", bookedSlots.length);

    if (tempCalendarEvent) {
        var tempEventDate = moment(tempCalendarEvent.start).format("YYYY-MM-DD");
        if (tempCalendarEvent.title === facilityName && tempEventDate === selectedDate) {
            bookedSlots.push(tempCalendarEvent);
        }
    }

    $('.slot-btn').each(function () {
        var $button = $(this);
        var slotStart = $button.data('start');
        var slotEnd = $button.data('end');

        if (!slotStart || !slotEnd) {
            return;
        }

        var slotStartTime = moment(selectedDate + ' ' + slotStart, 'YYYY-MM-DD HH:mm');
        var slotEndTime = moment(selectedDate + ' ' + slotEnd, 'YYYY-MM-DD HH:mm');

        var isPastTime = false;
        if (isToday) {
            isPastTime = slotEndTime.isBefore(now) || slotEndTime.isSameOrBefore(now);
        }

        var isBooked = bookedSlots.some(function (event) {
            var eventStart = moment(event.start);
            var eventEnd = moment(event.end);
            return (slotStartTime.isBefore(eventEnd) && slotEndTime.isAfter(eventStart));
        });

        if (isBooked) {
            $button.prop('disabled', true)
                .addClass('disabled booked')
                .removeClass('selected')
                .css({
                    'opacity': '0.5',
                    'cursor': 'not-allowed',
                    'background-color': '#e0e0e0',
                    'color': '#999',
                    'pointer-events': 'none'
                });

            if ($button.find('.badge-danger').length === 0) {
                $button.append(' <span class="badge badge-danger ml-2">Booked</span>');
            }
        } else if (isPastTime) {
            $button.prop('disabled', true)
                .addClass('disabled past-time')
                .removeClass('selected')
                .css({
                    'opacity': '0.4',
                    'cursor': 'not-allowed',
                    'background-color': '#f5f5f5',
                    'color': '#aaa',
                    'pointer-events': 'none'
                });

            if ($button.find('.badge-secondary').length === 0) {
                $button.append(' <span class="badge badge-secondary ml-2">Past</span>');
            }
            $button.find('.badge-danger').remove();
        } else {
            $button.prop('disabled', false)
                .removeClass('disabled booked past-time')
                .css({
                    'opacity': '',
                    'cursor': '',
                    'background-color': '',
                    'color': '',
                    'pointer-events': ''
                });
            $button.find('.badge-danger, .badge-secondary').remove();
        }
    });
}

/**
 * Save reservation data to payment section
 */
function saveToPaymentSection() {
    var facilityName = $("#selected_facility").val() || selectedFacility;
    var phone = tempModalData.phone;
    var startDate = $("#event_start_date").val();
    var endDate = $("#event_end_date").val();
    var timeStart = $("#selected_time_start").val();
    var timeEnd = $("#selected_time_end").val();
    var note = tempModalData.note;

    if (!facilityName) {
        Swal.fire({
            icon: "warning",
            title: "Facility Required",
            text: "Please select a facility from the cards."
        });
        return;
    }

    if (!phone || phone.length !== 11 || !phone.startsWith('09') || /^(\d)\1{10}$/.test(phone)) {
        Swal.fire({
            icon: "error",
            title: "Invalid Phone Number",
            text: "Please enter a valid 11-digit Philippine mobile number starting with 09."
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

    // Save to actual reservationData
    reservationData.date = moment(startDate).format("MMMM DD, YYYY");
    reservationData.timeStart = moment(timeStart, 'HH:mm').format('h:mm A');
    reservationData.timeEnd = moment(timeEnd, 'HH:mm').format('h:mm A');
    reservationData.phone = phone;
    reservationData.note = note;

    calculateAndUpdateCost();

    var eventStartDateTime = moment(startDate + ' ' + timeStart, 'YYYY-MM-DD HH:mm');
    var eventEndDateTime = moment(startDate + ' ' + timeEnd, 'YYYY-MM-DD HH:mm');

    tempCalendarEvent = {
        title: facilityName,
        start: eventStartDateTime.format('YYYY-MM-DD HH:mm:ss'),
        end: eventEndDateTime.format('YYYY-MM-DD HH:mm:ss'),
        color: '#ffc107',
        textColor: '#000',
        isPending: true
    };

    updateSummaryDisplay();
    load_events();
    checkNextButtonState();

    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };

    Swal.fire({
        icon: "success",
        title: "Reservation Saved!",
        text: "Your booking details have been saved. You can now proceed to the payment step.",
        confirmButtonText: "OK"
    });

    $("#myModal").modal("hide");
    clearModalForm();
}

/**
 * Update summary display
 */
function updateSummaryDisplay() {
    $('.summary-card').each(function () {
        var $summaryCard = $(this);

        if (reservationData.facility) {
            $summaryCard.find('.facility-name').text(reservationData.facility);
        } else {
            $summaryCard.find('.facility-name').text('No facility selected');
        }

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

        var formattedPrice = '₱0.00';
        if (reservationData.totalCost > 0) {
            formattedPrice = '₱' + reservationData.totalCost.toFixed(2);
        }
        $summaryCard.find('.breakdown-price').text(formattedPrice);
        $summaryCard.find('.total-price').text(formattedPrice);
    });
}

/**
 * Calculate and update cost
 */
function calculateAndUpdateCost() {
    if (!reservationData.facility || !reservationData.timeStart || !reservationData.timeEnd) {
        reservationData.totalCost = 0;
        return;
    }

    var facilityPrices = {
        'Chapel': 500,
        'Basketball Court': 100,
        'Multipurpose Hall': 600,
        'Tennis Court': 400
    };

    var costPerHour = facilityPrices[reservationData.facility] || 350;
    var start = moment(reservationData.timeStart, 'h:mm A');
    var end = moment(reservationData.timeEnd, 'h:mm A');
    var hours = end.diff(start, 'hours', true);
    reservationData.totalCost = costPerHour * hours;
}

/**
 * Clear modal form fields
 */
function clearModalForm() {
    $('#phone').val('');
    $('#event_note').val('');
    $('#selected_time_start').val('');
    $('#selected_time_end').val('');
    $('.slot-btn').removeClass('selected');
    $('#phone').removeClass('is-invalid is-valid');
    $('#phoneFeedback').hide();

    tempModalData = {
        phone: '',
        note: '',
        timeStart: '',
        timeEnd: '',
        selectedDate: ''
    };

    $('#saveReservationBtn').prop('disabled', true).addClass('disabled');
}

/**
 * Get reservation data
 */
function getReservationData() {
    return reservationData;
}

/**
 * Save final reservation to database with cost calculation
 */
function saveFinalReservation() {
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
    formData.append('cost', reservationData.totalCost); // Send calculated cost
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
                    text: "Your reservation has been saved successfully and is pending approval.",
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

                    // Redirect to my reservations page
                    window.location.href = "../my-reservations/myreservations.php";
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Reservation Failed",
                    text: response.msg || "Error saving reservation. Please try again.",
                    confirmButtonText: "OK"
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
                console.error("Could not parse error response:", e);
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