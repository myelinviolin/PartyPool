<?php
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if column exists
$check_query = "SELECT COUNT(*) as count FROM information_schema.columns
                WHERE table_schema = DATABASE()
                AND table_name = 'users'
                AND column_name = 'assigned_driver_id'";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute();
$result = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] == 0) {
    // Add the column
    $alter_query = "ALTER TABLE users ADD COLUMN assigned_driver_id INT DEFAULT NULL AFTER is_assigned_driver";
    try {
        $db->exec($alter_query);
        echo "Column 'assigned_driver_id' added successfully to users table.\n";
    } catch (PDOException $e) {
        echo "Error adding column: " . $e->getMessage() . "\n";
    }
} else {
    echo "Column 'assigned_driver_id' already exists in users table.\n";
}
?>