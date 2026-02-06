<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inline Update Test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Inline Update Test</h2>

        <div class="alert alert-info">
            Session: <?php echo isset($_SESSION['admin_id']) ? '✅ Active' : '❌ Not active'; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <input type="hidden" id="eventId" value="1">
                <input type="text" id="eventName" class="form-control mb-2" value="Test Event">
                <input type="text" id="eventAddress" class="form-control mb-2" value="Wisconsin State Capitol, Madison, WI">
                <input type="date" id="eventDate" class="form-control mb-2" value="2026-02-08">
                <input type="time" id="eventTime" class="form-control mb-2" value="19:00">

                <button class="btn btn-primary" onclick="updateEventSimple()">Update Event (Simple)</button>
                <button class="btn btn-success" onclick="updateEventInline()">Update Event (Inline)</button>
            </div>
        </div>

        <div id="result" class="mt-3"></div>

        <pre id="log" class="mt-3 p-3 bg-dark text-light" style="height: 300px; overflow: auto;">
Ready...
</pre>
    </div>

    <script>
        function log(msg) {
            document.getElementById('log').textContent += '\n' + new Date().toLocaleTimeString() + ': ' + msg;
        }

        // Simplest possible version
        function updateEventSimple() {
            log('updateEventSimple called');

            fetch('update_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: '1',
                    event_name: 'Simple Test',
                    event_address: 'Madison, WI',
                    event_date: '2026-02-08',
                    event_time: '19:00'
                })
            })
            .then(r => {
                log('Response status: ' + r.status);
                return r.text();
            })
            .then(text => {
                log('Response: ' + text);
                document.getElementById('result').innerHTML = '<pre>' + text + '</pre>';
            })
            .catch(e => {
                log('Error: ' + e.message);
            });
        }

        // Inline version with data from form
        function updateEventInline() {
            log('updateEventInline called');

            const data = {
                id: document.getElementById('eventId').value,
                event_name: document.getElementById('eventName').value,
                event_address: document.getElementById('eventAddress').value,
                event_date: document.getElementById('eventDate').value,
                event_time: document.getElementById('eventTime').value
            };

            log('Data: ' + JSON.stringify(data));

            fetch('update_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => {
                log('Status: ' + response.status);
                return response.json();
            })
            .then(result => {
                log('Result: ' + JSON.stringify(result));

                if (result.success) {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-success">
                            ✅ Success! Geocoded to: ${result.lat}, ${result.lng}
                        </div>
                    `;
                } else {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-danger">
                            ❌ Failed: ${result.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                log('Error: ' + error);
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        Error: ${error.message}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>