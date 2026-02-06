<?php
session_start();

// Set admin session for this operation
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Adjusting Test Participants</title>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'>
</head>
<body>
<div class='container mt-4'>
<h2>Adjusting Test Participants</h2>";

try {
    // First, check current users
    $check_query = "SELECT COUNT(*) as total,
                   SUM(CASE WHEN willing_to_drive = 1 THEN 1 ELSE 0 END) as drivers,
                   SUM(CASE WHEN willing_to_drive = 0 THEN 1 ELSE 0 END) as riders
                   FROM users WHERE event_id = 1";
    $stmt = $db->prepare($check_query);
    $stmt->execute();
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='alert alert-info'>
            <strong>Current Status:</strong><br>
            Total: {$current['total']} users<br>
            Drivers: {$current['drivers']}<br>
            Riders: {$current['riders']}
          </div>";

    // Start transaction
    $db->beginTransaction();

    // Step 1: Delete all existing users except the first 12
    $delete_query = "DELETE FROM users
                    WHERE event_id = 1
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM users
                            WHERE event_id = 1
                            ORDER BY id
                            LIMIT 12
                        ) as keep_users
                    )";
    $db->exec($delete_query);
    echo "<p>✅ Removed excess users</p>";

    // Step 2: Update first 10 users to be drivers
    $driver_query = "UPDATE users
                    SET willing_to_drive = 1,
                        vehicle_capacity = FLOOR(2 + RAND() * 3),
                        vehicle_make = CASE
                            WHEN id % 5 = 0 THEN 'Toyota'
                            WHEN id % 5 = 1 THEN 'Honda'
                            WHEN id % 5 = 2 THEN 'Ford'
                            WHEN id % 5 = 3 THEN 'Chevrolet'
                            ELSE 'Nissan'
                        END,
                        vehicle_model = CASE
                            WHEN id % 5 = 0 THEN 'Camry'
                            WHEN id % 5 = 1 THEN 'Civic'
                            WHEN id % 5 = 2 THEN 'Explorer'
                            WHEN id % 5 = 3 THEN 'Malibu'
                            ELSE 'Altima'
                        END,
                        vehicle_color = CASE
                            WHEN id % 4 = 0 THEN 'Silver'
                            WHEN id % 4 = 1 THEN 'Black'
                            WHEN id % 4 = 2 THEN 'White'
                            ELSE 'Blue'
                        END
                    WHERE event_id = 1
                    AND id IN (
                        SELECT id FROM (
                            SELECT id FROM users
                            WHERE event_id = 1
                            ORDER BY id
                            LIMIT 10
                        ) as driver_users
                    )";
    $db->exec($driver_query);
    echo "<p>✅ Updated first 10 users as drivers</p>";

    // Step 3: Update last 2 users to be riders
    $rider_query = "UPDATE users
                   SET willing_to_drive = 0,
                       vehicle_capacity = 0,
                       vehicle_make = NULL,
                       vehicle_model = NULL,
                       vehicle_color = NULL
                   WHERE event_id = 1
                   AND id IN (
                       SELECT id FROM (
                           SELECT id FROM users
                           WHERE event_id = 1
                           ORDER BY id DESC
                           LIMIT 2
                       ) as rider_users
                   )";
    $db->exec($rider_query);
    echo "<p>✅ Updated last 2 users as riders</p>";

    // If we have less than 12 users, add new ones
    $count_query = "SELECT COUNT(*) as count FROM users WHERE event_id = 1";
    $stmt = $db->prepare($count_query);
    $stmt->execute();
    $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_count = $count_result['count'];

    if ($current_count < 12) {
        $needed = 12 - $current_count;

        // Add drivers if needed (up to 10 total)
        $drivers_query = "SELECT COUNT(*) as count FROM users WHERE event_id = 1 AND willing_to_drive = 1";
        $stmt = $db->prepare($drivers_query);
        $stmt->execute();
        $drivers_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $drivers_to_add = min($needed, 10 - $drivers_count);

        for ($i = 0; $i < $drivers_to_add; $i++) {
            $name = "Driver " . ($drivers_count + $i + 1);
            $email = "driver" . ($drivers_count + $i + 1) . "@example.com";
            $phone = "608-555-" . sprintf("%04d", 1000 + $drivers_count + $i);
            $address = (100 + $drivers_count + $i) . " Main St, Madison, WI 53703";
            $capacity = rand(2, 4);
            $makes = ['Toyota', 'Honda', 'Ford', 'Chevrolet', 'Nissan'];
            $models = ['Camry', 'Civic', 'Explorer', 'Malibu', 'Altima'];
            $colors = ['Silver', 'Black', 'White', 'Blue', 'Red'];

            $insert_query = "INSERT INTO users (event_id, name, email, phone, address, lat, lng,
                           willing_to_drive, vehicle_capacity, vehicle_make, vehicle_model, vehicle_color)
                           VALUES (1, :name, :email, :phone, :address,
                           43.0731 + (RAND() - 0.5) * 0.1,
                           -89.4012 + (RAND() - 0.5) * 0.1,
                           1, :capacity, :make, :model, :color)";
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':capacity' => $capacity,
                ':make' => $makes[array_rand($makes)],
                ':model' => $models[array_rand($models)],
                ':color' => $colors[array_rand($colors)]
            ]);
        }

        // Add riders if needed
        $riders_to_add = $needed - $drivers_to_add;
        for ($i = 0; $i < $riders_to_add; $i++) {
            $name = "Rider " . ($i + 1);
            $email = "rider" . ($i + 1) . "@example.com";
            $phone = "608-555-" . sprintf("%04d", 2000 + $i);
            $address = (200 + $i) . " Park Ave, Madison, WI 53703";

            $insert_query = "INSERT INTO users (event_id, name, email, phone, address, lat, lng,
                           willing_to_drive, vehicle_capacity)
                           VALUES (1, :name, :email, :phone, :address,
                           43.0731 + (RAND() - 0.5) * 0.1,
                           -89.4012 + (RAND() - 0.5) * 0.1,
                           0, 0)";
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address
            ]);
        }

        echo "<p>✅ Added $needed new users to reach 12 total</p>";
    }

    // Commit transaction
    $db->commit();

    // Final check
    $final_query = "SELECT COUNT(*) as total,
                   SUM(CASE WHEN willing_to_drive = 1 THEN 1 ELSE 0 END) as drivers,
                   SUM(CASE WHEN willing_to_drive = 0 THEN 1 ELSE 0 END) as riders
                   FROM users WHERE event_id = 1";
    $stmt = $db->prepare($final_query);
    $stmt->execute();
    $final = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='alert alert-success'>
            <strong>✅ Adjustment Complete!</strong><br>
            Total: {$final['total']} users<br>
            Drivers: {$final['drivers']}<br>
            Riders: {$final['riders']}
          </div>";

    // Display the users
    echo "<h3>Current Participants:</h3>";
    echo "<table class='table table-striped'>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Vehicle</th>
                    <th>Capacity</th>
                </tr>
            </thead>
            <tbody>";

    $list_query = "SELECT * FROM users WHERE event_id = 1 ORDER BY willing_to_drive DESC, name";
    $stmt = $db->prepare($list_query);
    $stmt->execute();

    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $user['willing_to_drive'] ? '<span class="badge bg-success">Driver</span>' : '<span class="badge bg-info">Rider</span>';
        $vehicle = $user['willing_to_drive'] ? "{$user['vehicle_color']} {$user['vehicle_make']} {$user['vehicle_model']}" : 'N/A';
        $capacity = $user['willing_to_drive'] ? $user['vehicle_capacity'] : 'N/A';

        echo "<tr>
                <td>{$user['name']}</td>
                <td>{$type}</td>
                <td>{$vehicle}</td>
                <td>{$capacity}</td>
              </tr>";
    }

    echo "</tbody></table>";

} catch (Exception $e) {
    $db->rollBack();
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

echo "<div class='mt-3'>
        <a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>
      </div>
      </div>
      </body>
      </html>";
?>