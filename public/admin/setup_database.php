<?php
// Database setup script using mysqli
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$username = 'root';
$password = '';
$database_name = 'partypool';

// Connect without selecting a database first
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    echo "<p style='color: red;'><strong>Connection Error:</strong> " . $conn->connect_error . "</p>";
    echo "<p>Troubleshooting: Make sure MySQL service (MySQL80) is running. You can check via Services in Windows or XAMPP Control Panel.</p>";
    echo "<p><a href='/index.html'>Go back to home</a></p>";
    exit;
}

echo "<p>✓ Connected to MySQL server</p>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $database_name DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Database '$database_name' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating database: " . $conn->error . "</p>";
}

// Select database
$conn->select_db($database_name);

// Create events table
$sql = "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(255),
    event_address VARCHAR(255),
    event_date DATE,
    event_time TIME,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Table 'events' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating events table: " . $conn->error . "</p>";
}

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    address VARCHAR(500),
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    willing_to_drive BOOLEAN DEFAULT FALSE,
    vehicle_capacity INT DEFAULT 4,
    vehicle_make VARCHAR(100),
    vehicle_model VARCHAR(100),
    vehicle_color VARCHAR(50),
    special_notes TEXT,
    is_assigned_driver BOOLEAN DEFAULT FALSE,
    assigned_driver_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_driver_id) REFERENCES users(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Table 'users' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating users table: " . $conn->error . "</p>";
}

// Create admins table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Table 'admins' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating admins table: " . $conn->error . "</p>";
}

// Create optimization_results table
$sql = "CREATE TABLE IF NOT EXISTS optimization_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT DEFAULT 1,
    routes LONGTEXT,
    vehicles_used INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event (event_id)
)";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Table 'optimization_results' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating optimization_results table: " . $conn->error . "</p>";
}

// Create carpool_assignments table
$sql = "CREATE TABLE IF NOT EXISTS carpool_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    driver_user_id INT NOT NULL,
    total_distance DECIMAL(10, 2),
    total_passengers INT,
    route_order LONGTEXT,
    optimization_score DECIMAL(10, 4),
    departure_time TIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === TRUE) {
    echo "<p>✓ Table 'carpool_assignments' created or already exists</p>";
} else {
    echo "<p style='color: red;'>Error creating carpool_assignments table: " . $conn->error . "</p>";
}

// Insert sample event
$check = $conn->query("SELECT COUNT(*) as count FROM events WHERE id = 1");
$result = $check->fetch_row();
if ($result[0] == 0) {
    $sql = "INSERT INTO events (id, name, description) VALUES (1, 'Sample Party', 'Sample event for testing')";
    if ($conn->query($sql)) {
        echo "<p>✓ Sample event created</p>";
    }
}

// Insert sample admin
$check = $conn->query("SELECT COUNT(*) as count FROM admins WHERE username = 'admin'");
$result = $check->fetch_row();
if ($result[0] == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $username_var = 'admin';
    $email_var = 'admin@partypool.local';
    $sql = "INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username_var, $hash, $email_var);
    if ($stmt->execute()) {
        echo "<p>✓ Admin user created (username: admin, password: admin123)</p>";
    } else {
        echo "<p style='color: red;'>Error creating admin: " . $stmt->error . "</p>";
    }
}

echo "<hr><p style='color: green; font-weight: bold;'>✅ Database setup complete!</p>";
echo "<p><strong>Login credentials:</strong></p>";
echo "<ul>";
echo "<li>Username: <code>admin</code></li>";
echo "<li>Password: <code>admin123</code></li>";
echo "</ul>";
echo "<p><a href='/admin/login.php'>Go to Login Page</a> | <a href='/index.html'>Go back to home</a></p>";

$conn->close();
?>
