<?php
session_start();

// Set session for testing if not logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    echo '<div class="alert alert-warning">Session set for testing. <a href="login.php">Login properly</a> for full functionality.</div>';
}

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get current event
$query = "SELECT * FROM events WHERE id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current = $stmt->fetch(PDO::FETCH_ASSOC);

// Test if update_event.php exists and is accessible
$updateEndpointExists = file_exists('update_event.php');
$updateEndpointReadable = is_readable('update_event.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Update Event Test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes slideDown {
            from {
                transform: translate(-50%, -100%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }
        .success-notification {
            animation: slideDown 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Live Update Event Test</h2>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">System Check</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><?php echo $_SESSION['admin_id'] ? '✅' : '❌'; ?> Session active (ID: <?php echo $_SESSION['admin_id'] ?? 'none'; ?>)</li>
                            <li><?php echo $updateEndpointExists ? '✅' : '❌'; ?> update_event.php exists</li>
                            <li><?php echo $updateEndpointReadable ? '✅' : '❌'; ?> update_event.php is readable</li>
                            <li><?php echo $current ? '✅' : '❌'; ?> Database connection working</li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Current Event Data</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($current['event_name']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($current['event_address']); ?></p>
                        <p><strong>Date:</strong> <?php echo $current['event_date']; ?></p>
                        <p><strong>Time:</strong> <?php echo $current['event_time']; ?></p>
                        <p><strong>Lat/Lng:</strong> <?php echo $current['event_lat']; ?>, <?php echo $current['event_lng']; ?></p>
                        <p><strong>Last Updated:</strong> <?php echo $current['updated_at']; ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card" id="updateCard">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Test Update Event</h5>
                    </div>
                    <div class="card-body">
                        <form id="testUpdateForm">
                            <div class="mb-3">
                                <label class="form-label">Event Name</label>
                                <input type="text" class="form-control" id="eventName"
                                       value="<?php echo htmlspecialchars($current['event_name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Address</label>
                                <input type="text" class="form-control" id="eventAddress"
                                       value="<?php echo htmlspecialchars($current['event_address']); ?>">
                                <small class="text-muted">Try: Wisconsin State Capitol, Madison, WI</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="eventDate"
                                       value="<?php echo $current['event_date']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="eventTime"
                                       value="<?php echo $current['event_time']; ?>">
                            </div>
                            <button type="button" onclick="testUpdate(this)" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Test Update Event
                            </button>
                        </form>

                        <div id="testResults" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Debug Console</h5>
            </div>
            <div class="card-body">
                <pre id="debugConsole" class="bg-dark text-light p-3" style="max-height: 300px; overflow-y: auto;">
Ready to test...
</pre>
            </div>
        </div>

        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <a href="test_update_address.php" class="btn btn-warning">Test Address Changes</a>
            <button onclick="testDirectUpdate()" class="btn btn-danger">Test Direct Database Update</button>
        </div>
    </div>

    <script>
        function log(message) {
            const console = document.getElementById('debugConsole');
            console.innerHTML += message + '\n';
            console.scrollTop = console.scrollHeight;
        }

        async function testUpdate(btn) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';

            const eventData = {
                id: 1,
                event_name: document.getElementById('eventName').value,
                event_address: document.getElementById('eventAddress').value,
                event_date: document.getElementById('eventDate').value,
                event_time: document.getElementById('eventTime').value
            };

            log('=== Starting Update Test ===');
            log('Sending data: ' + JSON.stringify(eventData, null, 2));

            try {
                const response = await fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(eventData)
                });

                log('Response status: ' + response.status);
                const text = await response.text();
                log('Response text: ' + text);

                let result;
                try {
                    result = JSON.parse(text);
                    log('Parsed result: ' + JSON.stringify(result, null, 2));
                } catch (e) {
                    log('ERROR: Response is not valid JSON');
                    result = { success: false, message: 'Invalid JSON response: ' + text };
                }

                const resultsDiv = document.getElementById('testResults');

                if (result.success) {
                    // Show success message like in dashboard
                    const pageAlert = document.createElement('div');
                    pageAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 success-notification shadow-lg';
                    pageAlert.style.zIndex = '9999';
                    pageAlert.style.minWidth = '400px';
                    pageAlert.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <strong>Event Updated Successfully!</strong><br>
                                <small>${eventData.event_name} has been updated.</small>
                                ${result.lat && result.lng ? `<br><small>Location geocoded: ${eventData.event_address}</small>` : ''}
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(pageAlert);

                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✅ Update Successful!</strong><br>
                            ${result.lat ? `Geocoded to: ${result.lat}, ${result.lng}` : 'No geocoding performed'}
                        </div>
                    `;

                    log('✅ UPDATE SUCCESSFUL');

                    // Reload after delay to show new data
                    setTimeout(() => {
                        log('Reloading page to show updated data...');
                        location.reload();
                    }, 2000);

                } else {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>❌ Update Failed!</strong><br>
                            ${result.message || 'Unknown error'}
                        </div>
                    `;
                    log('❌ UPDATE FAILED: ' + (result.message || 'Unknown error'));
                }

            } catch (error) {
                log('❌ ERROR: ' + error.message);
                document.getElementById('testResults').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Connection Error!</strong><br>
                        ${error.message}
                    </div>
                `;
            }

            btn.disabled = false;
            btn.innerHTML = originalText;
        }

        async function testDirectUpdate() {
            log('=== Testing Direct Database Update ===');

            try {
                const response = await fetch('test_update_address.php');
                const text = await response.text();
                log('Direct update test loaded - check the page');
                window.open('test_update_address.php', '_blank');
            } catch (error) {
                log('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>