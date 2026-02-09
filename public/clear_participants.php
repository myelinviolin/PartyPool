<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Clear all participants for event 1
        $stmt = $db->prepare("DELETE FROM users WHERE event_id = 1");
        $stmt->execute();

        $count = $stmt->rowCount();

        // Also clear any optimization results
        $stmt = $db->prepare("DELETE FROM optimization_results WHERE event_id = 1");
        $stmt->execute();

        // Reset event status
        $stmt = $db->prepare("UPDATE events SET is_optimized = 0 WHERE event_id = 1");
        $stmt->execute();

        $_SESSION['message'] = "Successfully cleared $count test participants.";
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error clearing participants: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
}

// Redirect back
header("Location: check_participants.php");
exit();
?>