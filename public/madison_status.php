<?php
include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get event info
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->query($event_query);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Get participants
$users_query = "SELECT name, address, willing_to_drive, vehicle_capacity
                FROM users
                WHERE event_id = 1
                ORDER BY willing_to_drive DESC, name";
$users_stmt = $db->query($users_query);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$willing_count = 0;
$total_capacity = 0;
foreach($users as $user) {
    if ($user['willing_to_drive']) {
        $willing_count++;
        $total_capacity += $user['vehicle_capacity'] ?: 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Madison Carpool Status</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">
            <i class="fas fa-car"></i> Madison Carpool Status
        </h1>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Event Details</h4>
            </div>
            <div class="card-body">
                <p><strong>Event:</strong> <?php echo htmlspecialchars($event['event_name']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($event['event_address']); ?></p>
                <p><strong>Date/Time:</strong> <?php echo $event['event_date'] . ' at ' . $event['event_time']; ?></p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo count($users); ?></h3>
                        <p class="mb-0">Total Participants</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo $willing_count; ?></h3>
                        <p class="mb-0">Willing to Drive</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo $total_capacity; ?></h3>
                        <p class="mb-0">Total Vehicle Capacity</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">All Madison Participants</h4>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Madison Address</th>
                            <th>Status</th>
                            <th>Vehicle Capacity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['address']); ?></td>
                            <td>
                                <?php if ($user['willing_to_drive']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-car"></i> Can Drive
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info">
                                        <i class="fas fa-user"></i> Needs Ride
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['willing_to_drive'] && $user['vehicle_capacity']): ?>
                                    <?php echo $user['vehicle_capacity']; ?> seats
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i> Back to Main App
            </a>
            <a href="/admin/login.php" class="btn btn-success">
                <i class="fas fa-user-shield"></i> Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html>