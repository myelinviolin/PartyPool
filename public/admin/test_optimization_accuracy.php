<?php
session_start();
$_SESSION['admin_id'] = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Optimization Accuracy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .test-result {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .test-pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .test-fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .test-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
        }
        .test-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .card-preview {
            display: inline-block;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .card-blue { background: #cce5ff; }
        .card-orange { background: #ffe4d1; }
        .card-green { background: #d4edda; }
        .card-unknown { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Test Optimization Accuracy</h2>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            This test verifies that participant cards correctly reflect optimization results.
            All drivers in the optimization should have blue cards, all passengers should have orange cards.
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Test Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="vehicleCount" class="form-label">Number of Vehicles (optional):</label>
                            <input type="number" id="vehicleCount" class="form-control" min="1" max="10" placeholder="Leave empty for auto">
                        </div>
                        <button class="btn btn-primary" onclick="runTest()">
                            <i class="fas fa-play"></i> Run Test
                        </button>
                        <button class="btn btn-secondary" onclick="clearResults()">
                            <i class="fas fa-trash"></i> Clear Results
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Test Status</h5>
                    </div>
                    <div class="card-body" id="statusArea">
                        <p class="text-muted">Click "Run Test" to start verification...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Test Results</h5>
            </div>
            <div class="card-body" id="resultsArea">
                <p class="text-muted">No test results yet...</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Detailed Verification</h5>
            </div>
            <div class="card-body" id="detailedArea">
                <p class="text-muted">Detailed verification will appear here after running the test...</p>
            </div>
        </div>

        <!-- Hidden iframe to load dashboard -->
        <iframe id="dashboardFrame" style="display: none;"></iframe>
    </div>

    <script>
        let testResults = {
            optimization: null,
            cardStates: null,
            errors: [],
            warnings: [],
            passes: []
        };

        async function runTest() {
            const statusArea = document.getElementById('statusArea');
            const resultsArea = document.getElementById('resultsArea');
            const detailedArea = document.getElementById('detailedArea');

            // Clear previous results
            testResults = {
                optimization: null,
                cardStates: null,
                errors: [],
                warnings: [],
                passes: []
            };

            statusArea.innerHTML = '<div class="loading-spinner"></div> Running optimization...';
            resultsArea.innerHTML = '<p class="text-muted">Test in progress...</p>';
            detailedArea.innerHTML = '<p class="text-muted">Collecting data...</p>';

            try {
                // Step 1: Run optimization
                const vehicleCount = document.getElementById('vehicleCount').value || null;
                statusArea.innerHTML = '<div class="loading-spinner"></div> Running optimization...';

                const optimizationResult = await runOptimization(vehicleCount);
                testResults.optimization = optimizationResult;

                if (!optimizationResult.success) {
                    throw new Error('Optimization failed: ' + optimizationResult.message);
                }

                statusArea.innerHTML = '<div class="loading-spinner"></div> Waiting for cards to update...';

                // Step 2: Wait a moment for cards to update
                await new Promise(resolve => setTimeout(resolve, 1000));

                // Step 3: Get participant cards state
                statusArea.innerHTML = '<div class="loading-spinner"></div> Analyzing participant cards...';
                const cardStates = await getParticipantCards();
                testResults.cardStates = cardStates;

                // Step 4: Verify results
                statusArea.innerHTML = '<div class="loading-spinner"></div> Verifying accuracy...';
                verifyAccuracy();

                // Step 5: Display results
                displayResults();

                // Update status
                const totalTests = testResults.passes.length + testResults.errors.length;
                const passRate = totalTests > 0 ? Math.round((testResults.passes.length / totalTests) * 100) : 0;

                statusArea.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>Test Complete!</strong><br>
                            Pass Rate: ${passRate}%
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">${testResults.passes.length} Passed</span>
                            <span class="badge bg-danger">${testResults.errors.length} Failed</span>
                            <span class="badge bg-warning">${testResults.warnings.length} Warnings</span>
                        </div>
                    </div>
                `;

            } catch (error) {
                statusArea.innerHTML = `<div class="alert alert-danger mb-0">Error: ${error.message}</div>`;
                resultsArea.innerHTML = '<p class="text-danger">Test failed to complete.</p>';
            }
        }

        async function runOptimization(targetVehicles) {
            const response = await fetch('optimize_enhanced.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    event_id: 1,
                    target_vehicles: targetVehicles ? parseInt(targetVehicles) : null,
                    max_drive_time: 50
                })
            });

            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Failed to parse optimization response');
            }
        }

        async function getParticipantCards() {
            // Load dashboard in hidden iframe and extract card states
            return new Promise((resolve) => {
                const iframe = document.getElementById('dashboardFrame');
                iframe.onload = function() {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    const cards = iframeDoc.querySelectorAll('.participant-card');

                    const cardStates = [];
                    cards.forEach(card => {
                        const name = card.dataset.userName || 'Unknown';
                        const isBlue = card.classList.contains('assigned-driver');
                        const isOrange = card.classList.contains('assigned-rider');
                        const isGreen = card.classList.contains('can-drive');
                        const badge = card.querySelector('.assignment-status')?.innerText || '';

                        cardStates.push({
                            name: name,
                            color: isBlue ? 'blue' : (isOrange ? 'orange' : (isGreen ? 'green' : 'unknown')),
                            badge: badge.trim(),
                            element: {
                                classes: Array.from(card.classList)
                            }
                        });
                    });

                    resolve(cardStates);
                };

                iframe.src = 'dashboard.php?event_id=1';
            });
        }

        function verifyAccuracy() {
            if (!testResults.optimization || !testResults.cardStates) {
                testResults.errors.push('Missing data for verification');
                return;
            }

            const routes = testResults.optimization.routes || [];
            const cards = testResults.cardStates || [];

            // Create a map of cards by name for easy lookup
            const cardMap = new Map();
            cards.forEach(card => {
                cardMap.set(card.name, card);
                // Also try without "Driver X - " prefix
                const nameMatch = card.name.match(/Driver \d+ - (.+)/);
                if (nameMatch) {
                    cardMap.set(nameMatch[1], card);
                }
                // Also try without "Rider X - " prefix
                const riderMatch = card.name.match(/Rider \d+ - (.+)/);
                if (riderMatch) {
                    cardMap.set(riderMatch[1], card);
                }
            });

            // Track all verified assignments
            const verifiedAssignments = new Set();

            // Verify each driver in optimization results
            routes.forEach((route, index) => {
                const driverName = route.driver_name;
                const card = findCard(cardMap, driverName);

                if (!card) {
                    testResults.errors.push(`Driver "${driverName}" from route ${index + 1} not found in participant cards`);
                    return;
                }

                verifiedAssignments.add(card.name);

                // Check if driver has blue card
                if (card.color !== 'blue') {
                    testResults.errors.push(`Driver "${driverName}" has ${card.color} card instead of blue`);
                } else {
                    testResults.passes.push(`Driver "${driverName}" correctly has blue card`);
                }

                // Check badge
                const hasPassengers = route.passengers && route.passengers.length > 0;
                if (hasPassengers) {
                    if (!card.badge.includes('Driving - picking up passengers')) {
                        testResults.warnings.push(`Driver "${driverName}" badge mismatch: "${card.badge}" (expected "Driving - picking up passengers")`);
                    } else {
                        testResults.passes.push(`Driver "${driverName}" has correct badge for picking up passengers`);
                    }
                } else {
                    if (!card.badge.includes('Driving Solo')) {
                        testResults.warnings.push(`Driver "${driverName}" badge mismatch: "${card.badge}" (expected "Driving Solo")`);
                    } else {
                        testResults.passes.push(`Driver "${driverName}" has correct solo driving badge`);
                    }
                }

                // Verify passengers
                if (route.passengers) {
                    route.passengers.forEach(passenger => {
                        const passengerCard = findCard(cardMap, passenger.name);

                        if (!passengerCard) {
                            testResults.errors.push(`Passenger "${passenger.name}" not found in participant cards`);
                            return;
                        }

                        verifiedAssignments.add(passengerCard.name);

                        // Check if passenger has orange card
                        if (passengerCard.color !== 'orange') {
                            testResults.errors.push(`Passenger "${passenger.name}" has ${passengerCard.color} card instead of orange`);
                        } else {
                            testResults.passes.push(`Passenger "${passenger.name}" correctly has orange card`);
                        }

                        // Check badge
                        const expectedBadge = `Riding with #${driverName}`;
                        if (!passengerCard.badge.includes('Riding with')) {
                            testResults.errors.push(`Passenger "${passenger.name}" missing "Riding with" badge`);
                        } else if (!passengerCard.badge.includes(driverName)) {
                            testResults.warnings.push(`Passenger "${passenger.name}" badge shows wrong driver: "${passengerCard.badge}"`);
                        } else {
                            testResults.passes.push(`Passenger "${passenger.name}" has correct riding badge`);
                        }
                    });
                }
            });

            // Check for unverified cards (potential unassigned participants)
            cards.forEach(card => {
                if (!verifiedAssignments.has(card.name)) {
                    if (card.badge && card.badge.length > 0) {
                        testResults.warnings.push(`Card "${card.name}" has badge "${card.badge}" but wasn't in optimization results`);
                    }
                    // This is ok - some people might not be assigned
                }
            });

            // Summary checks
            const driversInOptimization = routes.length;
            const blueCards = cards.filter(c => c.color === 'blue').length;

            if (driversInOptimization !== blueCards) {
                testResults.errors.push(`Driver count mismatch: ${driversInOptimization} in optimization, ${blueCards} blue cards`);
            } else {
                testResults.passes.push(`Driver count matches: ${driversInOptimization} drivers with blue cards`);
            }

            const passengersInOptimization = routes.reduce((total, route) =>
                total + (route.passengers ? route.passengers.length : 0), 0);
            const orangeCardsWithBadge = cards.filter(c =>
                c.color === 'orange' && c.badge.includes('Riding with')).length;

            if (passengersInOptimization !== orangeCardsWithBadge) {
                testResults.errors.push(`Passenger count mismatch: ${passengersInOptimization} in optimization, ${orangeCardsWithBadge} orange cards with "Riding with" badges`);
            } else {
                testResults.passes.push(`Passenger count matches: ${passengersInOptimization} passengers with orange cards`);
            }
        }

        function findCard(cardMap, name) {
            // Try exact match
            if (cardMap.has(name)) {
                return cardMap.get(name);
            }

            // Try to find partial matches
            for (const [cardName, card] of cardMap) {
                if (cardName.includes(name) || name.includes(cardName)) {
                    return card;
                }
            }

            return null;
        }

        function displayResults() {
            const resultsArea = document.getElementById('resultsArea');
            const detailedArea = document.getElementById('detailedArea');

            // Display summary
            let resultsHtml = '<h6>Test Summary:</h6>';

            if (testResults.errors.length === 0) {
                resultsHtml += '<div class="test-pass"><i class="fas fa-check-circle"></i> All critical tests passed!</div>';
            } else {
                resultsHtml += '<div class="test-fail"><i class="fas fa-times-circle"></i> Some tests failed. See details below.</div>';
            }

            resultsHtml += '<div class="mt-3">';
            resultsHtml += `<p><strong>Optimization Results:</strong></p>`;
            resultsHtml += `<ul>`;
            resultsHtml += `<li>Total routes: ${testResults.optimization.routes ? testResults.optimization.routes.length : 0}</li>`;
            resultsHtml += `<li>Vehicles needed: ${testResults.optimization.vehicles_needed}</li>`;
            resultsHtml += `<li>Total participants: ${testResults.optimization.total_participants}</li>`;
            resultsHtml += `</ul>`;
            resultsHtml += '</div>';

            resultsArea.innerHTML = resultsHtml;

            // Display detailed results
            let detailedHtml = '';

            // Passes
            if (testResults.passes.length > 0) {
                detailedHtml += '<h6 class="text-success">✓ Passed Tests:</h6>';
                testResults.passes.forEach(pass => {
                    detailedHtml += `<div class="test-pass"><i class="fas fa-check"></i> ${pass}</div>`;
                });
            }

            // Errors
            if (testResults.errors.length > 0) {
                detailedHtml += '<h6 class="text-danger mt-3">✗ Failed Tests:</h6>';
                testResults.errors.forEach(error => {
                    detailedHtml += `<div class="test-fail"><i class="fas fa-times"></i> ${error}</div>`;
                });
            }

            // Warnings
            if (testResults.warnings.length > 0) {
                detailedHtml += '<h6 class="text-warning mt-3">⚠ Warnings:</h6>';
                testResults.warnings.forEach(warning => {
                    detailedHtml += `<div class="test-warning"><i class="fas fa-exclamation-triangle"></i> ${warning}</div>`;
                });
            }

            // Card states
            detailedHtml += '<h6 class="mt-3">Participant Card States:</h6>';
            detailedHtml += '<div class="test-info">';
            testResults.cardStates.forEach(card => {
                const colorClass = card.color === 'blue' ? 'card-blue' :
                                  card.color === 'orange' ? 'card-orange' :
                                  card.color === 'green' ? 'card-green' : 'card-unknown';
                detailedHtml += `<div class="card-preview ${colorClass}">`;
                detailedHtml += `<strong>${card.name}</strong>`;
                if (card.badge) {
                    detailedHtml += ` - ${card.badge}`;
                }
                detailedHtml += '</div>';
            });
            detailedHtml += '</div>';

            detailedArea.innerHTML = detailedHtml;
        }

        function clearResults() {
            document.getElementById('statusArea').innerHTML = '<p class="text-muted">Click "Run Test" to start verification...</p>';
            document.getElementById('resultsArea').innerHTML = '<p class="text-muted">No test results yet...</p>';
            document.getElementById('detailedArea').innerHTML = '<p class="text-muted">Detailed verification will appear here after running the test...</p>';
        }
    </script>
</body>
</html>