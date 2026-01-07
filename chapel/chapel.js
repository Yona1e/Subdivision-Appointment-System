// Calendar + Time Slot Integration
document.addEventListener('DOMContentLoaded', function () {

    const calendarEl = document.getElementById('calendar');
    let selectedDate = null;
    let selectedStartTime = null;
    let selectedEndTime = null;

    // Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,

        dateClick: function (info) {
            selectedDate = info.dateStr;

            // Reset selected slot
            selectedStartTime = null;
            selectedEndTime = null;
            document.querySelectorAll('.slot-btn')
                .forEach(b => b.classList.remove('active'));

            // Show modal
            const myModal = new bootstrap.Modal(
                document.getElementById('myModal')
            );
            myModal.show();
        }
    });

    calendar.render();

    // ===============================
    // Phone input validation
    // ===============================
    const phoneInput = document.getElementById('phone');

    phoneInput.addEventListener('input', function () {
        if (/[^0-9]/.test(this.value)) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });

    // ===============================
    // Time Slot Button Selection
    // ===============================
    document.querySelectorAll('.slot-btn').forEach(btn => {
        btn.addEventListener('click', () => {

            // Remove active from all
            document.querySelectorAll('.slot-btn')
                .forEach(b => b.classList.remove('active'));

            // Add active to clicked
            btn.classList.add('active');

            // Store selected times
            selectedStartTime = btn.dataset.start;
            selectedEndTime = btn.dataset.end;
        });
    });

    // ===============================
    // Save Event Button
    // ===============================
    document.getElementById('saveEvent').addEventListener('click', function () {

        const username = document.getElementById('username').value.trim();
        const phone = phoneInput.value.trim();
        const phoneIsValid = /^[0-9]*$/.test(phone);

        // Validation
        if (!username || !selectedStartTime) {
            alert("Username and Time Slot are required");
            return;
        }

        if (!phoneIsValid) {
            phoneInput.classList.add('is-invalid');
            return;
        }

        // Add event to calendar
        calendar.addEvent({
            title: username,
            start: selectedDate + 'T' + selectedStartTime,
            end: selectedDate + 'T' + selectedEndTime,
            allDay: false
        });

        // Clear inputs
        document.getElementById('username').value = '';
        phoneInput.value = '';
        phoneInput.classList.remove('is-invalid');

        selectedStartTime = null;
        selectedEndTime = null;

        document.querySelectorAll('.slot-btn')
            .forEach(b => b.classList.remove('active'));

        // Hide modal
        const myModalEl = document.getElementById('myModal');
        const modalInstance = bootstrap.Modal.getInstance(myModalEl);
        modalInstance.hide();
    });

});

document.querySelectorAll('.slot-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.slot-btn')
            .forEach(b => b.classList.remove('selected'));

        btn.classList.add('selected');
    });
});
