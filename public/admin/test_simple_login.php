<?php
session_start();

// Clear any existing session
session_destroy();
session_start();

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test with provided credentials
$username = 'admin';
$password = 'Admin2024!';

$query = "SELECT id, username, password_hash FROM admins WHERE username = :username";
$stmt = $db->prepare($query);
$stmt->bindParam(':username', $username);
$stmt->execute();

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Login Test Page</h2>";
echo "<p>Testing credentials: admin / Admin2024!</p>";

if ($admin) {
    echo "<p>✓ Admin found in database</p>";
    echo "<p>Hash from DB: " . substr($admin['password_hash'], 0, 20) . "...</p>";

    if (password_verify($password, $admin['password_hash'])) {
        echo "<p style='color: green;'>✓ Password verification successful!</p>";
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        echo "<p>Session set. You should be able to access the dashboard.</p>";
        echo "<a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>";
    } else {
        echo "<p style='color: red;'>✗ Password verification failed</p>";

        // Fix it
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_query = "UPDATE admins SET password_hash = :hash WHERE username = 'admin'";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':hash', $new_hash);
        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>✓ Password has been reset. Refresh this page to login.</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Admin user not found</p>";

    // Create admin
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert_query = "INSERT INTO admins (username, password_hash, email) VALUES ('admin', :hash, 'admin@partycarpool.com')";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':hash', $hash);
    if ($insert_stmt->execute()) {
        echo "<p style='color: green;'>✓ Admin user created. Refresh this page to login.</p>";
    }
}
?>

<br><br>
<h3>Manual Login Form</h3>
<form method="POST" action="login.php">
    <input type="text" name="username" value="admin" placeholder="Username"><br><br>
    <input type="password" name="password" value="Admin2024!" placeholder="Password"><br><br>
    <button type="submit">Login via Regular Form</button>
</form>