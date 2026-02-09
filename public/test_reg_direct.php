<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed\n");
}

// Test data
$data = [
    "name" => "Test User " . time(),
    "email" => "test" . time() . "@example.com",
    "phone" => "555-1234",
    "willing_to_drive" => true,
    "address" => "123 Test St, Madison, WI 53703",
    "lat" => 43.0731,
    "lng" => -89.4012,
    "event_id" => 1,
    "special_notes" => "Test registration",
    "vehicle_capacity" => 4,
    "vehicle_make" => "Toyota",
    "vehicle_model" => "Camry",
    "vehicle_color" => "Blue"
];

echo "Testing registration with data:\n";
print_r($data);
echo "\n";

// Prepare the query
$query = "INSERT INTO users (name, email, phone, willing_to_drive, vehicle_capacity,
          vehicle_make, vehicle_model, vehicle_color, preferred_departure_time,
          address, lat, lng, special_notes, event_id)
          VALUES (:name, :email, :phone, :willing_to_drive, :vehicle_capacity,
          :vehicle_make, :vehicle_model, :vehicle_color, :departure_time,
          :address, :lat, :lng, :special_notes, :event_id)";

try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':phone', $data['phone']);

    $willing_to_drive = (bool)$data['willing_to_drive'];
    $stmt->bindParam(':willing_to_drive', $willing_to_drive, PDO::PARAM_BOOL);

    $stmt->bindParam(':vehicle_capacity', $data['vehicle_capacity']);
    $stmt->bindParam(':vehicle_make', $data['vehicle_make']);
    $stmt->bindParam(':vehicle_model', $data['vehicle_model']);
    $stmt->bindParam(':vehicle_color', $data['vehicle_color']);

    // departure_time is not provided, set to null
    $null = null;
    $stmt->bindParam(':departure_time', $null);

    $stmt->bindParam(':address', $data['address']);
    $stmt->bindParam(':lat', $data['lat']);
    $stmt->bindParam(':lng', $data['lng']);
    $stmt->bindParam(':special_notes', $data['special_notes']);
    $stmt->bindParam(':event_id', $data['event_id']);

    $stmt->execute();
    $user_id = $db->lastInsertId();

    echo "SUCCESS: User created with ID: $user_id\n";

    // Verify the insert
    $verify = $db->query("SELECT * FROM users WHERE id = $user_id");
    $user = $verify->fetch(PDO::FETCH_ASSOC);
    echo "\nVerification - User data saved:\n";
    print_r($user);

} catch(PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nSQL State: " . $e->getCode() . "\n";
}