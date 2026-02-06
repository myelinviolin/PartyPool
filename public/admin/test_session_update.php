<?php
session_start();

// Force set session for testing
if (isset($_GET['force_login'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    echo "Session forced. Redirecting...";
    header("Location: test_session_update.php");
    exit;
}

// Clear session if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: test_session_update.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session & Update Event Test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Session & Update Event Test</h2>

        <!-- Session Status -->
        <div class="card mb-3">
            <div class="card-header <?php echo isset($_SESSION['admin_id']) ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                <h5 class="mb-0">Current Session Status</h5>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <div class="alert alert-success">
                        <h5>✅ Logged In</h5>
                        <p>Admin ID: <?php echo $_SESSION['admin_id']; ?><br>
                        Username: <?php echo $_SESSION['admin_username']; ?><br>
                        Session ID: <?php echo substr(session_id(), 0, 10); ?>...</p>
                        <a href="?logout=1" class="btn btn-danger">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h5>❌ Not Logged In</h5>
                        <p>You need to be logged in for the Update Event button to work.</p>
                        <a href="?force_login=1" class="btn btn-success">Force Login (Test Only)</a>
                        <a href="login.php" class="btn btn-primary">Normal Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Event Test -->
        <?php if (isset($_SESSION['admin_id'])): ?>
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Update Event Test</h5>
            </div>
            <div class="card-body">
                <form id="eventForm">
                    <input type="hidden" id="eventId" value="1">

                    <div class="mb-3">
                        <label class="form-label">Event Name</label>
                        <input type="text" class="form-control" id="eventName"
                               value="Test Event - <?php echo date('H:i:s'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Event Address (Try Different Addresses)</label>
                        <select class="form-select" id="addressSelect" onchange="document.getElementById('eventAddress').value = this.value">
                            <option value="Wisconsin State Capitol, Madison, WI">Wisconsin State Capitol</option>
                            <option value="Monona Terrace, Madison, WI">Monona Terrace</option>
                            <option value="1 University Ave, Madison, WI">University Ave</option>
                            <option value="Memorial Union, Madison, WI">Memorial Union</option>
                        </select>
                        <input type="text" class="form-control mt-2" id="eventAddress"
                               value="Wisconsin State Capitol, Madison, WI">
                    </div>

                    <button type="button" class="btn btn-primary btn-lg w-100" onclick="updateEvent(this)">
                        <i class="fas fa-save"></i> Update Event
                    </button>
                </form>

                <div id="result" class="mt-3"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Direct PHP Test -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Direct PHP Update Test</h5>
            </div>
            <div class="card-body">
                <button onclick="testDirectUpdate()" class="btn btn-info">Test Direct Database Update</button>
                <div id="directResult" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
        async function updateEvent(btn) {
            const resultDiv = document.getElementById('result');

            // Show loading
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';

            const data = {
                id: document.getElementById('eventId').value,
                event_name: document.getElementById('eventName').value,
                event_address: document.getElementById('eventAddress').value,
                event_date: '2026-02-08',
                event_time: '19:00'
            };

            try {
                const response = await fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h5>✅ Update Successful!</h5>
                            <p>Event Name: ${data.event_name}<br>
                            Address: ${data.event_address}<br>
                            Coordinates: ${result.lat}, ${result.lng}</p>
                            <a href="../index.html" target="_blank" class="btn btn-sm btn-success">View on Homepage</a>
                        </div>
                    `;

                    // Add visual feedback
                    btn.closest('.card').classList.add('border-success');
                    setTimeout(() => {
                        btn.closest('.card').classList.remove('border-success');
                    }, 2000);

                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h5>❌ Update Failed</h5>
                            <p>${result.message}</p>
                            ${result.message === 'Unauthorized' ? '<a href="login.php" class="btn btn-primary">Login</a>' : ''}
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>❌ Error</h5>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        async function testDirectUpdate() {
            const resultDiv = document.getElementById('directResult');
            resultDiv.innerHTML = '<div class="spinner-border"></div> Testing...';

            try {
                const response = await fetch('test_update_comprehensive.php');
                const text = await response.text();

                if (text.includes('ALL TESTS PASSED')) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h5>✅ Direct Update Works!</h5>
                            <p>Database connection and updates are working correctly.</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <h5>⚠️ Partial Success</h5>
                            <p>Some components are working. Check browser console for details.</p>
                        </div>
                    `;
                }
                console.log(text);
            } catch (error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>