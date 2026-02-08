<?php
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "CREATE TABLE IF NOT EXISTS optimization_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    routes JSON,
    vehicles_used INT,
    created_at DATETIME,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event (event_id)
)";

try {
    $db->exec($query);
    echo "Table 'optimization_results' created successfully or already exists.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>