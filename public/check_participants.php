<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed\n");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Current Participants</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .driver { background-color: #e8f5e9; }
        .passenger { background-color: #fff3e0; }
        button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Current Participants in Event</h1>";

// Get participant count
$stmt = $db->query("SELECT COUNT(*) as total,
                    SUM(willing_to_drive = 1) as drivers,
                    SUM(willing_to_drive = 0) as passengers
                    FROM users WHERE event_id = 1");
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<div style='background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>";
echo "<h2>Summary</h2>";
echo "<p><strong>Total Participants:</strong> {$counts['total']}</p>";
echo "<p><strong>Willing to Drive:</strong> {$counts['drivers']}</p>";
echo "<p><strong>Need Rides:</strong> {$counts['passengers']}</p>";
echo "</div>";

// Get all participants
$stmt = $db->query("SELECT * FROM users WHERE event_id = 1 ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($users) > 0) {
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Can Drive?</th>
            <th>Seats</th>
            <th>Vehicle</th>
            <th>Address</th>
            <th>Created</th>
          </tr>";

    foreach ($users as $user) {
        $rowClass = $user['willing_to_drive'] ? 'driver' : 'passenger';
        echo "<tr class='{$rowClass}'>";
        echo "<td>{$user['id']}</td>";
        echo "<td><strong>{$user['name']}</strong></td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['phone']}</td>";
        echo "<td>" . ($user['willing_to_drive'] ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td>" . ($user['vehicle_capacity'] ?: '-') . "</td>";
        echo "<td>" . ($user['vehicle_make'] ? "{$user['vehicle_make']} {$user['vehicle_model']} ({$user['vehicle_color']})" : '-') . "</td>";
        echo "<td>" . substr($user['address'], 0, 30) . "...</td>";
        echo "<td>" . date('M d, H:i', strtotime($user['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div style='margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;'>";
    echo "<h3>⚠️ Need to Clear Test Data?</h3>";
    echo "<p>If these are all test participants and you need to start fresh:</p>";
    echo "<form method='POST' action='clear_participants.php' onsubmit='return confirm(\"Are you sure you want to delete ALL participants? This cannot be undone!\")'>";
    echo "<button type='submit'>Clear All Test Participants</button>";
    echo "</form>";
    echo "</div>";

} else {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>";
    echo "<h3>No Participants Found</h3>";
    echo "<p>There are currently no participants registered for this event.</p>";
    echo "<p>Go to the <a href='/'>main page</a> and click 'Register' to add participants.</p>";
    echo "</div>";
}

echo "</body></html>";
?>