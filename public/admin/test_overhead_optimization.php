<?php
session_start();
$_SESSION['admin_id'] = 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Overhead Optimization</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Overhead Optimization (< 20 minutes priority)</h2>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>New Feature:</strong> The optimization algorithm now automatically adjusts vehicle count to keep overhead under 20 minutes!
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Test Automatic Optimization</h5>
                    </div>
                    <div class="card-body">
                        <p>Run optimization without specifying vehicles:</p>
                        <button class="btn btn-primary" onclick="runOptimization(null)">
                            <i class="fas fa-magic"></i> Auto-Optimize (< 20 min overhead)
                        </button>
                        <div id="autoResults" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Test Manual Vehicle Count</h5>
                    </div>
                    <div class="card-body">
                        <p>Test with specific vehicle counts:</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-warning" onclick="runOptimization(2)">2 Vehicles</button>
                            <button class="btn btn-outline-warning" onclick="runOptimization(3)">3 Vehicles</button>
                            <button class="btn btn-outline-warning" onclick="runOptimization(4)">4 Vehicles</button>
                            <button class="btn btn-outline-warning" onclick="runOptimization(5)">5 Vehicles</button>
                        </div>
                        <div id="manualResults" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Optimization Details</h5>
            </div>
            <div class="card-body">
                <div id="detailedResults"></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">How It Works</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Initial Calculation:</strong> Starts with minimum vehicles based on capacity</li>
                    <li><strong>Overhead Check:</strong> Runs optimization and checks max overhead</li>
                    <li><strong>Auto-Adjustment:</strong> If any driver has >20 min overhead, adds vehicles</li>
                    <li><strong>Re-optimization:</strong> Continues until all overhead is <20 min or max drivers reached</li>
                    <li><strong>Geographic Clustering:</strong> Groups nearby participants to minimize travel</li>
                </ol>

                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle"></i>
                    <strong>Result:</strong> Drivers spend less time on pickups, making carpooling more appealing!
                </div>
            </div>
        </div>
    </div>

    <script>
        async function runOptimization(targetVehicles) {
            const resultsDiv = targetVehicles === null ? 'autoResults' : 'manualResults';
            const detailedDiv = document.getElementById('detailedResults');

            document.getElementById(resultsDiv).innerHTML = '<div class="spinner-border spinner-border-sm"></div> Running...';

            try {
                const response = await fetch('optimize_enhanced.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        event_id: 1,
                        target_vehicles: targetVehicles,
                        max_drive_time: 50
                    })
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    document.getElementById(resultsDiv).innerHTML = '<div class="alert alert-danger">Parse error</div>';
                    return;
                }

                if (result.success) {
                    let summary = `<div class="alert alert-success">
                        <strong>✅ Success!</strong><br>
                        Vehicles used: ${result.vehicles_needed}<br>`;

                    if (result.overhead_optimized) {
                        summary += `Max overhead: <strong>${result.max_overhead || 0} minutes</strong><br>`;
                        if (result.max_overhead <= 20) {
                            summary += `<span class="badge bg-success">All overhead < 20 min!</span>`;
                        }
                    }

                    summary += `</div>`;
                    document.getElementById(resultsDiv).innerHTML = summary;

                    // Show detailed results
                    let details = '<h5>Route Details:</h5>';
                    result.routes.forEach((route, idx) => {
                        const overhead = parseInt(route.overhead_time) || 0;
                        const badgeClass = overhead > 20 ? 'bg-warning' : 'bg-success';

                        details += `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span><strong>Driver ${idx + 1}:</strong> ${route.driver_name}</span>
                                        <span class="badge ${badgeClass}">${overhead} min overhead</span>
                                    </div>
                                    <small class="text-muted">
                                        ${route.passengers.length} passengers |
                                        Direct: ${route.direct_time} |
                                        Total: ${route.estimated_travel_time}
                                    </small>
                                </div>
                            </div>
                        `;
                    });

                    detailedDiv.innerHTML = details;
                } else {
                    document.getElementById(resultsDiv).innerHTML =
                        '<div class="alert alert-danger">Error: ' + result.message + '</div>';
                }
            } catch (error) {
                document.getElementById(resultsDiv).innerHTML =
                    '<div class="alert alert-danger">Network error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>