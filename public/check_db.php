<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed\n");
}

// Check table structure
$stmt = $db->query('DESCRIBE users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users table structure:\n";
echo "----------------------\n";
foreach ($columns as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}