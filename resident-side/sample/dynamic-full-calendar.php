<!DOCTYPE html>
<html>
<head>
<title>Dynamic Event Calendar - PHP</title>

<!-- FullCalendar CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet" />

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<!-- Moment.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.20.1/moment.min.js"></script>

<!-- FullCalendar JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>

<!-- Bootstrap -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"/>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

<style>
#calendar {
    max-width: 900px;
    margin: 40px auto;
}
</style>
</head>

<body>

<div class="container">
    <h3 class="text-center mt-4 mb-3">Dynamic Event Calendar</h3>
    <div id="calendar"></div>
</div>

<!-- Modal for adding events -->
<div class="modal fade" id="event_entry_modal">
    <div class="modal-dialog modal-md">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Add New Event</h5>
                <button class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body">

                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" id="event_name" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group col">
                        <label>Start Date</label>
                        <input type="date" id="event_start_date" class="form-control">
                    </div>

                    <div class="form-group col">
                        <label>End Date</label>
                        <input type="date" id="event_end_date" class="form-control">
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" onclick="save_event()">Save</button>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    load_events();
});

function load_events() {
    $.ajax({
        url: "display_event.php",
        dataType: "json",
        success: function (response) {

            $("#calendar").fullCalendar('destroy');

            $("#calendar").fullCalendar({
                editable: true,
                selectable: true,
                defaultView: 'month',
                events: response.data,

                select: function(start, end) {
                    $("#event_start_date").val(moment(start).format("YYYY-MM-DD"));
                    $("#event_end_date").val(moment(end).format("YYYY-MM-DD"));
                    $("#event_entry_modal").modal("show");
                },

                eventClick: function(event) {
                    alert("Event ID: " + event.event_id);
                }
            });
        },
        error: function(xhr) {
            console.log(xhr.responseText);
            alert("Error loading events.");
        }
    });
}

function save_event() {
    $.ajax({
        url: "save_event.php",
        type: "POST",
        dataType: "json",
        data: {
            event_name: $("#event_name").val(),
            event_start_date: $("#event_start_date").val(),
            event_end_date: $("#event_end_date").val()
        },
        success: function(response) {

            if (response.status === true) {
                alert(response.msg);
                $("#event_entry_modal").modal("hide");
                load_events();
            } else {
                alert(response.msg);
            }
        },
        error: function(xhr) {
            console.log(xhr.responseText);
            alert("Error saving event.");
        }
    });
}
</script>

</body>
</html>
