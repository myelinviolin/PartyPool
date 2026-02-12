<?php
// Quick script to check columns in the events table
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("DESCRIBE events");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$needed = ['event_name', 'event_address', 'event_date', 'event_time'];
$missing = array_diff($needed, $columns);

echo "Columns in events table:\n";
echo implode(", ", $columns) . "\n\n";
if (empty($missing)) {
    echo "All required columns are present.\n";
} else {
    echo "Missing columns: " . implode(", ", $missing) . "\n";
}
