<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Diagnostics</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Admin System Diagnostics</h2>

        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Session Status</h5>
            </div>
            <div class="card-body">
                <?php
                if (isset($_SESSION['admin_id'])) {
                    echo '<div class="alert alert-success">';
                    echo '<strong>✅ Logged In</strong><br>';
                    echo 'Admin ID: ' . $_SESSION['admin_id'] . '<br>';
                    echo 'Username: ' . $_SESSION['admin_username'];
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo '<strong>⚠️ Not Logged In</strong><br>';
                    echo 'Session ID: ' . session_id() . '<br>';
                    echo 'Session variables: ' . print_r($_SESSION, true);
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Test Login</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-2">
                        <input type="text" name="test_username" class="form-control" placeholder="Username" value="admin">
                    </div>
                    <div class="mb-2">
                        <input type="password" name="test_password" class="form-control" placeholder="Password" value="partyTime123!">
                    </div>
                    <button type="submit" name="test_login" class="btn btn-primary">Test Login</button>
                </form>

                <?php
                if (isset($_POST['test_login'])) {
                    include_once '../config/database.php';
                    $database = new Database();
                    $db = $database->getConnection();

                    $username = $_POST['test_username'];
                    $password = $_POST['test_password'];

                    $query = "SELECT * FROM admins WHERE username = :username";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    echo '<div class="mt-3">';
                    if ($admin && password_verify($password, $admin['password_hash'])) {
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        echo '<div class="alert alert-success">✅ Login successful! Session set. <a href="dashboard.php">Go to Dashboard</a></div>';
                    } else {
                        echo '<div class="alert alert-danger">❌ Login failed. ';
                        if ($admin) {
                            echo 'Password incorrect.';
                        } else {
                            echo 'User not found.';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-warning">
                <h5 class="mb-0">Test API Endpoints</h5>
            </div>
            <div class="card-body">
                <button onclick="testUpdateEvent()" class="btn btn-warning mb-2">Test Update Event</button>
                <button onclick="testOptimize()" class="btn btn-success mb-2">Test Optimization</button>
                <div id="apiResults"></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">File Permissions</h5>
            </div>
            <div class="card-body">
                <?php
                $files = [
                    'update_event.php',
                    'optimize.php',
                    'dashboard.php',
                    'login.php'
                ];

                echo '<ul class="list-unstyled">';
                foreach ($files as $file) {
                    $path = '/var/www/partycarpool.clodhost.com/public/admin/' . $file;
                    if (file_exists($path)) {
                        $perms = fileperms($path);
                        $owner = posix_getpwuid(fileowner($path))['name'];
                        $readable = is_readable($path) ? '✅' : '❌';
                        $writable = is_writable($path) ? '✅' : '❌';
                        $executable = is_executable($path) ? '✅' : '❌';

                        echo "<li><strong>$file</strong>: ";
                        echo "R:$readable W:$writable X:$executable ";
                        echo "Owner: $owner ";
                        echo "Perms: " . decoct($perms & 0777);
                        echo "</li>";
                    } else {
                        echo "<li><strong>$file</strong>: ❌ Not found</li>";
                    }
                }
                echo '</ul>';
                ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="login.php" class="btn btn-primary">Go to Login</a>
            <?php if (isset($_SESSION['admin_id'])): ?>
                <a href="dashboard.php" class="btn btn-success">Go to Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function testUpdateEvent() {
            const results = document.getElementById('apiResults');
            results.innerHTML = '<div class="spinner-border"></div> Testing Update Event...';

            try {
                const response = await fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: 1,
                        event_name: 'Test Event',
                        event_address: '615 State Street, Madison, WI',
                        event_date: '2026-02-08',
                        event_time: '19:00'
                    })
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    result = { error: 'Non-JSON response', response: text };
                }

                results.innerHTML = `
                    <div class="alert ${response.ok ? 'alert-success' : 'alert-danger'}">
                        <strong>Update Event Test:</strong><br>
                        Status: ${response.status}<br>
                        Response: ${JSON.stringify(result, null, 2)}
                    </div>
                `;
            } catch (error) {
                results.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        async function testOptimize() {
            const results = document.getElementById('apiResults');
            results.innerHTML = '<div class="spinner-border"></div> Testing Optimization...';

            try {
                const response = await fetch('optimize.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: 1 })
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    result = { error: 'Non-JSON response', response: text.substring(0, 500) };
                }

                results.innerHTML = `
                    <div class="alert ${response.ok ? 'alert-success' : 'alert-danger'}">
                        <strong>Optimization Test:</strong><br>
                        Status: ${response.status}<br>
                        Response: ${JSON.stringify(result, null, 2)}
                    </div>
                `;
            } catch (error) {
                results.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>