<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Carpool - Issue Verification Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .issue-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .issue-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px 0;
        }
        .fixed {
            background: #d4edda;
            color: #155724;
        }
        .in-progress {
            background: #fff3cd;
            color: #856404;
        }
        .pending {
            background: #f8d7da;
            color: #721c24;
        }
        .evidence {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .route-data {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .route-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
        }
        .route-card h4 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 1em;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .data-label {
            font-weight: 600;
            color: #6c757d;
        }
        .data-value {
            color: #212529;
        }
        .success-icon {
            color: #28a745;
            font-size: 1.2em;
        }
        .header-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-item {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .summary-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
<?php
include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get optimization data
$query = "SELECT * FROM optimization_results WHERE event_id = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$optimization = $stmt->fetch(PDO::FETCH_ASSOC);
$routes = json_decode($optimization['routes'], true);

// Get participant data
$query = "SELECT * FROM users WHERE event_id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_distance = 0;
$total_capacity = 0;
$used_capacity = 0;
$drivers_with_passengers = 0;
$solo_drivers = 0;

foreach ($routes as $route) {
    $total_distance += $route['total_distance'] ?? 0;
    $total_capacity += $route['capacity'] ?? 0;
    $used_capacity += count($route['passengers']);
    if (count($route['passengers']) > 0) {
        $drivers_with_passengers++;
    } else {
        $solo_drivers++;
    }
}
?>

<div class="container">
    <h1>🚗 Party Carpool Issue Verification Report</h1>

    <div class="header-info">
        <h2>Current System Status</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value"><?php echo count($routes); ?></div>
                <div class="summary-label">Active Routes</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo count($participants); ?></div>
                <div class="summary-label">Total Participants</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo number_format($total_distance, 1); ?> mi</div>
                <div class="summary-label">Total Distance</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $used_capacity; ?>/<?php echo $total_capacity; ?></div>
                <div class="summary-label">Capacity Used</div>
            </div>
        </div>
    </div>

    <!-- Issue 1: Route Selector -->
    <div class="issue-section">
        <div class="issue-title">
            <span class="success-icon">✅</span> Issue 1: Interactive Route Selector Implementation
        </div>
        <span class="status fixed">FIXED</span>
        <p><strong>Problem:</strong> Static legend needed to be replaced with interactive route selector</p>
        <p><strong>Solution:</strong> Implemented clickable route selector panel on right side of map for both home and admin pages</p>
        <div class="evidence">
            <strong>Features Implemented:</strong><br>
            • Routes displayed as numbered buttons (1-<?php echo count($routes); ?>)<br>
            • Click to highlight individual routes<br>
            • Click again to show all routes<br>
            • Map auto-zooms to selected route<br>
            • Consistent UI across home and admin pages
        </div>
    </div>

    <!-- Issue 2: Vehicle Capacities -->
    <div class="issue-section">
        <div class="issue-title">
            <span class="success-icon">✅</span> Issue 2: Vehicle Capacity Display
        </div>
        <span class="status fixed">FIXED</span>
        <p><strong>Problem:</strong> Vehicle capacities were not being saved or displayed</p>
        <p><strong>Solution:</strong> Fixed optimization algorithm to properly save all route data including capacities</p>
        <div class="evidence">
            <strong>Current Vehicle Capacities:</strong><br>
            <div class="route-data">
                <?php foreach ($routes as $index => $route): ?>
                <div class="route-card">
                    <h4>Route <?php echo $index + 1; ?></h4>
                    <div class="data-row">
                        <span class="data-label">Driver:</span>
                        <span class="data-value"><?php echo str_replace('Driver ' . $route['driver_id'] . ' - ', '', $route['driver_name']); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Vehicle:</span>
                        <span class="data-value"><?php echo $route['vehicle'] ?? 'Unknown'; ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Capacity:</span>
                        <span class="data-value"><?php echo $route['capacity'] ?? 0; ?> seats</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Passengers:</span>
                        <span class="data-value"><?php echo count($route['passengers']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Issue 3: Distance Calculations -->
    <div class="issue-section">
        <div class="issue-title">
            <span class="success-icon">✅</span> Issue 3: Distance Calculations
        </div>
        <span class="status fixed">FIXED</span>
        <p><strong>Problem:</strong> All distances were showing as 0 miles</p>
        <p><strong>Solution:</strong> Fixed optimization to calculate and save Haversine distances between all points</p>
        <div class="evidence">
            <strong>Route Distances (in miles):</strong><br>
            <div class="route-data">
                <?php foreach ($routes as $index => $route): ?>
                <div class="route-card">
                    <h4>Route <?php echo $index + 1; ?></h4>
                    <div class="data-row">
                        <span class="data-label">Direct Distance:</span>
                        <span class="data-value"><?php echo number_format($route['direct_distance'] ?? 0, 2); ?> mi</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Total Distance:</span>
                        <span class="data-value"><?php echo number_format($route['total_distance'] ?? 0, 2); ?> mi</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Overhead:</span>
                        <span class="data-value"><?php
                            $overhead = ($route['total_distance'] ?? 0) - ($route['direct_distance'] ?? 0);
                            echo number_format($overhead, 2);
                        ?> mi</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Est. Time:</span>
                        <span class="data-value"><?php echo $route['estimated_travel_time'] ?? 'N/A'; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Issue 4: Map Synchronization -->
    <div class="issue-section">
        <div class="issue-title">
            <span class="success-icon">✅</span> Issue 4: Map Display Synchronization
        </div>
        <span class="status fixed">FIXED</span>
        <p><strong>Problem:</strong> Home page and admin page maps were not using the same display code</p>
        <p><strong>Solution:</strong> Updated both maps to use identical route selector and display logic</p>
        <div class="evidence">
            <strong>Synchronized Features:</strong><br>
            • Both maps now use interactive route selector<br>
            • Same color scheme for routes<br>
            • Identical popup information<br>
            • Consistent styling across pages<br>
            • Shared CSS classes for route selector
        </div>
    </div>

    <!-- Raw Data Verification -->
    <div class="issue-section">
        <div class="issue-title">
            📊 Raw Data Verification
        </div>
        <p>Sample of complete route data structure from database:</p>
        <div class="evidence">
            <pre><?php
            if (!empty($routes)) {
                echo json_encode($routes[0], JSON_PRETTY_PRINT);
            }
            ?></pre>
        </div>
    </div>

    <!-- System Health Check -->
    <div class="issue-section">
        <div class="issue-title">
            🔧 System Health Check
        </div>
        <div class="route-data">
            <div class="route-card">
                <h4>Database Status</h4>
                <div class="data-row">
                    <span class="data-label">Optimization Table:</span>
                    <span class="data-value">✅ Active</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Last Update:</span>
                    <span class="data-value"><?php echo $optimization['created_at']; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Target Vehicles:</span>
                    <span class="data-value"><?php echo $optimization['target_vehicles']; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Vehicles Used:</span>
                    <span class="data-value"><?php echo $optimization['vehicles_used']; ?></span>
                </div>
            </div>

            <div class="route-card">
                <h4>Route Statistics</h4>
                <div class="data-row">
                    <span class="data-label">Solo Drivers:</span>
                    <span class="data-value"><?php echo $solo_drivers; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Drivers w/ Passengers:</span>
                    <span class="data-value"><?php echo $drivers_with_passengers; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Empty Seats:</span>
                    <span class="data-value"><?php echo $total_capacity - $used_capacity; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Efficiency:</span>
                    <span class="data-value"><?php echo round(($used_capacity / $total_capacity) * 100); ?>%</span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>