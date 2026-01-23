let selectedFacility = null;
let selectedTimeSlot = null;
let currentBookings = [];

$(document).ready(function () {
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

    // Time slot selection
    $(document).on('click', '.slot-btn:not(.disabled):not(:disabled)', function () {
        $('.slot-btn').removeClass('selected');
        $(this).addClass('selected');

        selectedTimeSlot = {
            start: $(this).data('start'),
            end: $(this).data('end')
        };

        // Update hidden fields
        $('#selected_time_start').val(selectedTimeSlot.start);
        $('#selected_time_end').val(selectedTimeSlot.end);

        validateForm();
    });

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
        const phoneValid = /^09\d{9}$/.test(phone);
        const timeSlotSelected = selectedTimeSlot !== null;

        if (!phoneValid && phone.length > 0) {
            $('#phone').addClass('is-invalid');
        } else {
            $('#phone').removeClass('is-invalid');
        }

        $('#saveReservationBtn').prop('disabled', !(phoneValid && timeSlotSelected));
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

        if (!/^09\d{9}$/.test(phone)) {
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
            start: moment().format('YYYY-MM-DD') // Prevent past date selection
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
                'approved': '#10b981',    // Green
                'confirmed': '#10b981',   // Green
                'pending': '#f59e0b',     // Yellow/Orange
                'rejected': '#ef4444'     // Red
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
            // Colors are already set in the event object from fetchBookings
            // This function just ensures proper rendering
            element.css('cursor', 'pointer');
        }
    });
}

// Fetch bookings from ALL users (regular users and admins)
// FIXED: Filter out rejected and cancelled reservations
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
        success: function (response) {
            if (response.status) {
                // FIXED: Filter out rejected and cancelled reservations
                // Only show and check conflicts with pending and approved bookings
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
                    // Add color to each event based on status
                    const coloredEvents = activeBookings.map(event => {
                        const statusColors = {
                            'approved': '#10b981',    // Green
                            'confirmed': '#10b981',   // Green (same as approved)
                            'pending': '#f59e0b',     // Yellow/Orange
                            'rejected': '#ef4444'     // Red (won't appear due to filter)
                        };

                        return {
                            ...event,
                            backgroundColor: statusColors[event.status] || '#3b82f6',
                            borderColor: statusColors[event.status] || '#3b82f6'
                        };
                    });

                    $('#calendar').fullCalendar('addEventSource', coloredEvents);
                }

                // Update bookings list with ALL bookings (including rejected for display)
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
        error: function (xhr, status, error) {
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

    // Sort by date descending
    bookings.sort((a, b) => new Date(b.start) - new Date(a.start));

    let html = '';
    bookings.forEach(booking => {
        const startTime = moment(booking.start).format('h:mm A');
        const endTime = moment(booking.end).format('h:mm A');
        const date = moment(booking.start).format('MMM D, YYYY');

        const statusColors = {
            'approved': '#10b981',    // Green
            'confirmed': '#10b981',   // Green
            'pending': '#f59e0b',     // Yellow/Orange
            'rejected': '#ef4444'     // Red
        };

        const borderColor = statusColors[booking.status] || '#3b82f6';

        html += `
                    <div class="booking-item" style="border-left-color: ${borderColor};">
                        <h6>${booking.title}</h6>
                        <p><strong>Date:</strong> ${date}</p>
                        <p><strong>Time:</strong> ${startTime} - ${endTime}</p>
                        <p><strong>Status:</strong> <span style="color: ${borderColor}; font-weight: 600;">${booking.status.toUpperCase()}</span></p>
                        <p><strong>User Type:</strong> ${booking.user_role || 'Unknown'}</p>
                        ${booking.phone ? `<p><strong>Phone:</strong> ${booking.phone}</p>` : ''}
                    </div>
                `;
    });

    $list.html(html);
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
    $('#selected_time_start').val('');
    $('#selected_time_end').val('');
    $('#saveReservationBtn').prop('disabled', true);

    // Check and disable already booked slots
    checkAvailableSlots(formattedDate);

    $('#myModal').modal('show');
}

// FIXED: Check available slots and disable booked ones (only checks pending/approved)
// Rejected and cancelled reservations don't block time slots
function checkAvailableSlots(date) {
    // Only use currentBookings which already has rejected/cancelled filtered out
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

    $('.slot-btn').each(function () {
        const slotStart = $(this).data('start');
        const slotEnd = $(this).data('end');

        // Check if this slot conflicts with any booked slot
        const isBooked = bookedSlots.some(booked => {
            return (slotStart >= booked.start && slotStart < booked.end) ||
                (slotEnd > booked.start && slotEnd <= booked.end) ||
                (slotStart <= booked.start && slotEnd >= booked.end);
        });

        if (isBooked) {
            $(this).addClass('disabled').prop('disabled', true);
            console.log("Disabling slot:", slotStart, "-", slotEnd);
        }
    });
}

// Submit reservation - Creates APPROVED reservation directly (admin bypass)
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
            note: note
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