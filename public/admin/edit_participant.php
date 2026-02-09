<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Prepare update query
    $query = "UPDATE users SET
              name = :name,
              email = :email,
              phone = :phone,
              address = :address,
              willing_to_drive = :willing_to_drive,
              special_notes = :special_notes,
              vehicle_capacity = :vehicle_capacity,
              vehicle_make = :vehicle_make,
              vehicle_model = :vehicle_model,
              vehicle_color = :vehicle_color,
              updated_at = CURRENT_TIMESTAMP
              WHERE id = :id";

    $stmt = $db->prepare($query);

    // Bind parameters
    $stmt->bindParam(':id', $input['id']);
    $stmt->bindParam(':name', $input['name']);
    $stmt->bindParam(':email', $input['email']);
    $stmt->bindParam(':phone', $input['phone']);
    $stmt->bindParam(':address', $input['address']);

    $willing_to_drive = isset($input['willing_to_drive']) ? (bool)$input['willing_to_drive'] : false;
    $stmt->bindParam(':willing_to_drive', $willing_to_drive, PDO::PARAM_BOOL);

    $stmt->bindParam(':special_notes', $input['special_notes']);

    // Vehicle details
    if ($willing_to_drive && isset($input['vehicle_capacity'])) {
        $stmt->bindParam(':vehicle_capacity', $input['vehicle_capacity']);
        $stmt->bindParam(':vehicle_make', $input['vehicle_make']);
        $stmt->bindParam(':vehicle_model', $input['vehicle_model']);
        $stmt->bindParam(':vehicle_color', $input['vehicle_color']);
    } else {
        $null = null;
        $stmt->bindParam(':vehicle_capacity', $null);
        $stmt->bindParam(':vehicle_make', $null);
        $stmt->bindParam(':vehicle_model', $null);
        $stmt->bindParam(':vehicle_color', $null);
    }

    // Execute update
    if ($stmt->execute()) {
        // Geocode the address if it changed
        if (isset($input['address']) && !empty($input['address'])) {
            $address = urlencode($input['address']);
            $geocodeUrl = "https://nominatim.openstreetmap.org/search?format=json&q={$address}&limit=1";

            $context = stream_context_create([
                'http' => [
                    'header' => "User-Agent: PartyCarpool/1.0\r\n"
                ]
            ]);

            $geocodeResult = @file_get_contents($geocodeUrl, false, $context);

            if ($geocodeResult) {
                $geocodeData = json_decode($geocodeResult, true);

                if (!empty($geocodeData)) {
                    $lat = $geocodeData[0]['lat'];
                    $lng = $geocodeData[0]['lon'];

                    // Update coordinates
                    $updateCoords = "UPDATE users SET lat = :lat, lng = :lng WHERE id = :id";
                    $stmtCoords = $db->prepare($updateCoords);
                    $stmtCoords->bindParam(':lat', $lat);
                    $stmtCoords->bindParam(':lng', $lng);
                    $stmtCoords->bindParam(':id', $input['id']);
                    $stmtCoords->execute();
                }
            }
        }

        // If this participant's role changed (driver to passenger or vice versa), clear optimization
        $checkChange = "SELECT willing_to_drive FROM users WHERE id = :id";
        $checkStmt = $db->prepare($checkChange);
        $checkStmt->bindParam(':id', $input['id']);
        $checkStmt->execute();
        $currentStatus = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Clear optimization if status changed
        if ($currentStatus && $currentStatus['willing_to_drive'] != $willing_to_drive) {
            // Get event_id for this user
            $getEvent = "SELECT event_id FROM users WHERE id = :id";
            $eventStmt = $db->prepare($getEvent);
            $eventStmt->bindParam(':id', $input['id']);
            $eventStmt->execute();
            $eventData = $eventStmt->fetch(PDO::FETCH_ASSOC);

            if ($eventData) {
                // Clear optimization results
                $clearOpt = "DELETE FROM optimization_results WHERE event_id = :event_id";
                $clearStmt = $db->prepare($clearOpt);
                $clearStmt->bindParam(':event_id', $eventData['event_id']);
                $clearStmt->execute();

                // Clear carpool assignments
                $clearAssign = "DELETE FROM carpool_assignments WHERE event_id = :event_id";
                $clearStmt2 = $db->prepare($clearAssign);
                $clearStmt2->bindParam(':event_id', $eventData['event_id']);
                $clearStmt2->execute();

                // Update event status
                $updateEvent = "UPDATE events SET is_optimized = 0 WHERE event_id = :event_id";
                $updateStmt = $db->prepare($updateEvent);
                $updateStmt->bindParam(':event_id', $eventData['event_id']);
                $updateStmt->execute();
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Participant updated successfully']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update participant']);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Edit participant error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>