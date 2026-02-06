<?php
// Simulate admin session for testing
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';

// Test the optimization
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://localhost/admin/optimize.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event_id' => 1]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($result, true);

echo "Optimization Test Results:\n";
echo "HTTP Code: $http_code\n";
echo "Result: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";

if ($data['success']) {
    echo "\n✓ Optimization successful!\n";
    echo "- Total participants: " . $data['total_participants'] . "\n";
    echo "- Vehicles needed: " . $data['vehicles_needed'] . "\n";
    echo "- Vehicles saved: " . $data['vehicles_saved'] . "\n\n";

    foreach ($data['routes'] as $i => $route) {
        echo "Vehicle " . ($i + 1) . ": " . $route['driver_name'] . " driving " . $route['vehicle'] . "\n";
        echo "  Passengers: ";
        foreach ($route['passengers'] as $p) {
            echo $p['name'] . ", ";
        }
        echo "\n  Total distance: " . $route['total_distance'] . " miles\n\n";
    }
} else {
    echo "\n✗ Optimization failed: " . $data['message'] . "\n";
}
?>