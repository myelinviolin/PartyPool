<?php
// Direct test of optimization algorithm
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Include the optimizer class
include_once 'optimize.php';

// Run optimization
$optimizer = new CarpoolOptimizer($db, 1);
$result = $optimizer->optimize();

echo "Optimization Test Results:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
?>