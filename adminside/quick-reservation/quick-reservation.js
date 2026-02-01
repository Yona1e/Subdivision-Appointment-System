let selectedFacility = null;
let selectedTimeSlot = null;
let currentBookings = [];
let currentUserRole = 'Resident'; // Default to Resident, will be set from PHP session
let rangeStartSlot = null; // Track start of range selection

$(document).ready(function () {
    // Get user role from the page (should be set by PHP in a data attribute or hidden input)
    // Example: <body data-user-role="<?php echo $_SESSION['Role']; ?>">
    const userRoleFromPage = $('body').data('user-role') || $('#user_role').val();
    if (userRoleFromPage) {
        currentUserRole = userRoleFromPage;
    }

    // Initialize calendar
    initializeCalendar();

    // Facility dropdown change - REQUIRED BEFORE CALENDAR INTERACTION
    $('#facility_select').on('change', function () {
        selectedFacility = $(this).val();

        // Update facility name in modal
        $('#facility_name').val(selectedFacility);

        // Fetch bookings for selected facility (from ALL users)
        fetchBookings(selectedFacility);
    });

    // Time slot selection (Range Selection)
    $(document).on('click', '.slot-btn:not(.disabled):not(:disabled)', function () {
        let $clickedSlot = $(this);
        let clickedStart = $clickedSlot.data('start');
        let clickedEnd = $clickedSlot.data('end');

        if (rangeStartSlot === null) {
            // Start of a new range
            rangeStartSlot = $clickedSlot;
            $('.slot-btn').removeClass('selected');
            $clickedSlot.addClass('selected');
            updateSelection(clickedStart, clickedEnd);
        } else {
            let startDisplay = rangeStartSlot.data('start');

            if (clickedStart < startDisplay) {
                // Clicked earlier -> New Start
                rangeStartSlot = $clickedSlot;
                $('.slot-btn').removeClass('selected');
                $clickedSlot.addClass('selected');
                updateSelection(clickedStart, clickedEnd);
            } else if (clickedStart === startDisplay) {
                // Clicked Same -> Keep single
                updateSelection(clickedStart, clickedEnd);
            } else {
                // Range End
                let $allSlots = $('.slot-btn:not(.disabled):not(:disabled)'); // Only check enabled
                // Note: Indexing must be based on ALL slots to slice correctly?
                // Actually slice works on the jQuery collection.
                // But we need to check ALL slots (even disabled ones) between start and end.

                let $absoluteAllSlots = $('.slot-btn');
                let startIdx = $absoluteAllSlots.index(rangeStartSlot);
                let endIdx = $absoluteAllSlots.index($clickedSlot);

                let isValidRange = true;
                let $rangeSlots = $absoluteAllSlots.slice(startIdx, endIdx + 1);

                $rangeSlots.each(function () {
                    if ($(this).hasClass('disabled') || $(this).prop('disabled') || $(this).hasClass('booked')) {
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
                    // Reset to this new slot
                    rangeStartSlot = $clickedSlot;
                    $('.slot-btn').removeClass('selected');
                    $clickedSlot.addClass('selected');
                    updateSelection(clickedStart, clickedEnd);
                } else {
                    // Valid Range
                    $('.slot-btn').removeClass('selected');
                    $rangeSlots.addClass('selected');

                    let finalStartTime = rangeStartSlot.data('start');
                    let finalEndTime = clickedEnd;

                    updateSelection(finalStartTime, finalEndTime);

                    // Reset
                    rangeStartSlot = null;
                }
            }
        }
    });

    function updateSelection(start, end) {
        selectedTimeSlot = {
            start: start,
            end: end
        };
        // Update hidden fields
        $('#selected_time_start').val(start);
        $('#selected_time_end').val(end);
        validateForm();
    }

    // Phone input validation - numeric only
    $('#phone').on('input', function () {
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
        // Only valid check if phone is NOT empty. If empty, it's valid (optional).
        const startsWith09 = /^09/.test(phone);
        const isNumeric = /^\d+$/.test(phone);
        const isLength11 = phone.length === 11;

        let isPhoneValid = true;

        // If phone has value, check validity
        if (phone.length > 0) {
            if (!startsWith09 || !isNumeric || !isLength11) {
                isPhoneValid = false;
            }
        }

        const timeSlotSelected = selectedTimeSlot !== null;

        if (!isPhoneValid && phone.length > 0) {
            $('#phone').addClass('is-invalid');
        } else {
            $('#phone').removeClass('is-invalid');
        }

        // Enable if time slot is selected AND (phone is empty OR phone is valid)
        $('#saveReservationBtn').prop('disabled', !(timeSlotSelected && isPhoneValid));
    }

    // Submit reservation
    $('#saveReservationBtn').on('click', function () {
        const phone = $('#phone').val();
        const note = $('#event_note').val();
        const date = $('#event_start_date').val();
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

        if (phone.length > 0 && !/^09\d{9}$/.test(phone)) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Phone Number',
                text: 'Please enter a valid Philippine mobile number (e.g., 09123456789)',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Confirmation dialog
        Swal.fire({
            title: 'Confirm Reservation',
            html: `
                <div style="text-align: left; padding: 10px;">
                    <p><strong>Facility:</strong> ${facility}</p>
                    <p><strong>Date:</strong> ${moment(date).format('MMMM D, YYYY')}</p>
                    <p><strong>Time:</strong> ${selectedTimeSlot.start} - ${selectedTimeSlot.end}</p>
                    <p><strong>Phone:</strong> ${phone}</p>
                    ${note ? `<p><strong>Note:</strong> ${note}</p>` : ''}
                    <hr>
                    <p class="text-success"><strong>✓ This reservation will be automatically approved</strong></p>
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
});

function initializeCalendar() {
    // Determine header buttons based on screen size
    let rightButtons = 'month,agendaWeek,agendaDay';
    if (window.innerWidth < 576) {
        rightButtons = 'month';
    }

    // Determine aspect ratio based on screen size
    let aspectRatio = 1.8;
    if (window.innerWidth < 576) {
        aspectRatio = 1.0;
    } else if (window.innerWidth < 768) {
        aspectRatio = 1.3;
    } else if (window.innerWidth < 1024) {
        aspectRatio = 1.5;
    }

    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: rightButtons
        },
        editable: false,
        eventLimit: true,
        events: [],
        height: 'auto',
        contentHeight: 'auto',
        aspectRatio: aspectRatio,
        // validRange removed to show past dates

        // Handle window resize for responsive behavior
        windowResize: function (view) {
            let newAspectRatio = 1.8;
            let newButtons = 'month,agendaWeek,agendaDay';

            if (window.innerWidth < 576) {
                newAspectRatio = 1.0;
                newButtons = 'month';
            } else if (window.innerWidth < 768) {
                newAspectRatio = 1.3;
            } else if (window.innerWidth < 1024) {
                newAspectRatio = 1.5;
            }

            $('#calendar').fullCalendar('option', 'aspectRatio', newAspectRatio);
            $('#calendar').fullCalendar('option', 'header', {
                left: 'prev,next today',
                center: 'title',
                right: newButtons
            });
        },

        dayClick: function (date) {
            // CRITICAL: Prevent modal opening if no facility selected
            if (!selectedFacility) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Facility Selected',
                    text: 'Please select a facility first before choosing a date',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Prevent booking past dates
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

        eventClick: function (event) {
            const statusColors = {
                'approved': '#10b981',
                'confirmed': '#10b981',
                'pending': '#f59e0b',
                'rejected': '#ef4444'
            };

            Swal.fire({
                title: event.title,
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>Date:</strong> ${moment(event.start).format('MMMM D, YYYY')}</p>
                        <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                        <p><strong>Status:</strong> <span style="color: ${statusColors[event.status] || '#6b7280'}; font-weight: 600;">${event.status.toUpperCase()}</span></p>
                        <p><strong>User Type:</strong> ${event.user_role || 'Unknown'}</p>
                        ${event.email ? `<p><strong>Email:</strong> ${event.email}</p>` : ''}
                        ${event.phone ? `<p><strong>Phone:</strong> ${event.phone}</p>` : ''}
                        ${event.note ? `<p><strong>Note:</strong> ${event.note}</p>` : ''}
                    </div>
                `,
                icon: 'info',
                confirmButtonColor: '#3b82f6'
            });
        },

        eventRender: function (event, element) {
            element.css('cursor', 'pointer');
        }
    });
}

// Fetch bookings from ALL users (regular users and admins)
function fetchBookings(facility) {
    $('#calendar').fullCalendar('removeEvents');

    $.ajax({
        url: 'quick-reservation.php',
        type: 'GET',
        data: {
            action: 'fetch_bookings',
            facility: facility
        },
        dataType: 'json',
        success: function (response) {
            if (response.status) {
                // Filter out rejected and cancelled reservations
                const activeBookings = response.data.filter(booking => {
                    return booking.status === 'pending' || booking.status === 'approved' || booking.status === 'confirmed';
                });

                console.log("Total bookings from server:", response.data.length);
                console.log("Active bookings (pending/approved):", activeBookings.length);
                console.log("Filtered out:", response.data.length - activeBookings.length);

                currentBookings = activeBookings;

                // Update calendar with active bookings only
                $('#calendar').fullCalendar('removeEvents');
                if (activeBookings.length > 0) {
                    // COLOR IN THE CALENDAR
                    const coloredEvents = activeBookings.map(event => {
                        const statusColors = {
                            'approved': '#28a745',
                            'confirmed': '#28a745',
                            'pending': '#ffc107',
                            'rejected': '#dc3545'
                        };

                        return {
                            ...event,
                            backgroundColor: statusColors[event.status] || '#3b82f6',
                            borderColor: statusColors[event.status] || '#3b82f6'
                        };
                    });

                    $('#calendar').fullCalendar('addEventSource', coloredEvents);
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.msg || 'Failed to fetch bookings',
                    confirmButtonColor: '#3b82f6'
                });
            }
        },
        error: function (xhr, status, error) {
            console.error('Error fetching bookings:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Unable to fetch bookings. Please try again.',
                confirmButtonColor: '#3b82f6'
            });
        }
    });
}

function openBookingModal(date) {
    const formattedDate = date.format('YYYY-MM-DD');
    const displayDate = date.format('MMMM D, YYYY');

    $('#event_start_date').val(formattedDate);
    $('#event_end_date').val(formattedDate);
    $('#selected_facility').val(selectedFacility);
    $('#display_selected_date').text(displayDate);
    $('#facility_name').val(selectedFacility);

    // Reset form
    $('#phone').val('').removeClass('is-invalid');
    $('#event_note').val('');
    $('.slot-btn').removeClass('selected disabled').prop('disabled', false);
    selectedTimeSlot = null;
    rangeStartSlot = null; // Reset range selection logic
    $('#selected_time_start').val('');
    $('#selected_time_end').val('');
    $('#saveReservationBtn').prop('disabled', true);

    // Check and disable already booked slots
    checkAvailableSlots(formattedDate);

    $('#myModal').modal('show');
}

// Check available slots and disable booked ones
function checkAvailableSlots(date) {
    const bookedSlots = currentBookings
        .filter(b => {
            const bookingDate = moment(b.start).format('YYYY-MM-DD');
            const isActiveStatus = b.status === 'pending' || b.status === 'approved' || b.status === 'confirmed';
            return bookingDate === date && isActiveStatus;
        })
        .map(b => ({
            start: moment(b.start).format('HH:mm'),
            end: moment(b.end).format('HH:mm')
        }));

    console.log("Checking slots for date:", date);
    console.log("Booked slots found:", bookedSlots.length);

    // Check for past time slots if date is today
    const now = moment();
    const isToday = moment(date).isSame(now, 'day');

    $('.slot-btn').each(function () {
        const slotStart = $(this).data('start');
        const slotEnd = $(this).data('end');

        // Check if this slot conflicts with any booked slot
        const isBooked = bookedSlots.some(booked => {
            return (slotStart >= booked.start && slotStart < booked.end) ||
                (slotEnd > booked.start && slotEnd <= booked.end) ||
                (slotStart <= booked.start && slotEnd >= booked.end);
        });

        // Check if slot has passed (only for today)
        let isPast = false;
        if (isToday) {
            // changing the format to match the slot format for comparison
            const slotEndTime = moment(date + ' ' + slotEnd, 'YYYY-MM-DD HH:mm');
            if (slotEndTime.isBefore(now)) {
                isPast = true;
            }
        }

        if (isBooked) {
            $(this).addClass('disabled').prop('disabled', true);
            console.log("Disabling slot (booked):", slotStart, "-", slotEnd);
        } else if (isPast) {
            $(this).addClass('disabled').prop('disabled', true);
            $(this).css({ 'background-color': '#e9ecef', 'cursor': 'not-allowed', 'opacity': '0.6' }); // Visual feedback
            console.log("Disabling slot (past):", slotStart, "-", slotEnd);
        } else {
            // Ensure re-enabled if previously disabled (e.g. switching dates)
            if (!$(this).hasClass('booked')) { // Do not re-enable if it has class booked from somewhere else, although here we rebuild from scratch basically
                $(this).removeClass('disabled').prop('disabled', false);
                $(this).css({ 'background-color': '', 'cursor': '', 'opacity': '' });
            }
        }
    });
}

// Submit reservation - Creates APPROVED reservation directly (admin bypass)
// FIXED: Now includes user_role in the submission
function submitReservation(facility, date, timeStart, timeEnd, phone, note) {
    const $btn = $('#saveReservationBtn');
    const $spinner = $btn.find('.spinner-border');

    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

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
            note: note,
            user_role: currentUserRole  // FIXED: Now sending user_role
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Reservation created and approved successfully',
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    $('#myModal').modal('hide');
                    // Refresh bookings to show new approved reservation
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
        error: function (xhr, status, error) {
            console.error('Error submitting reservation:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to submit reservation. Please try again.',
                confirmButtonColor: '#3b82f6'
            });
        },
        complete: function () {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        }
    });
}