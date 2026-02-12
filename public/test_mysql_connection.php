<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing MySQL connection...\n\n";

// Check if mysqli extension is loaded
if (!extension_loaded('mysqli')) {
    echo "ERROR: mysqli extension is not loaded!\n";
    exit;
}

echo "✓ mysqli extension is loaded\n";

// Try to connect
$conn = new mysqli('127.0.0.1', 'root', '');

if ($conn->connect_error) {
    echo "✗ Connection failed: " . $conn->connect_error . "\n";
    echo "Error Code: " . $conn->connect_errno . "\n";
} else {
    echo "✓ Successfully connected to MySQL!\n";
    echo "Server Info: " . $conn->server_info . "\n";
    $conn->close();
}
?>

