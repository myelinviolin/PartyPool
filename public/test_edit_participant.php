<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Edit Participant Functionality</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #28a745;
            padding-bottom: 15px;
        }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        #results {
            margin-top: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Edit Participant Functionality</h1>

        <div class="test-section">
            <h2>Test Edit API</h2>
            <p>This will test editing a participant's information.</p>
            <button onclick="testEdit()">Test Edit Participant</button>
            <div id="results"></div>
        </div>

        <div class="test-section">
            <h2>Features Implemented:</h2>
            <ul>
                <li class="success">✅ Edit button added next to remove button for each participant</li>
                <li class="success">✅ Edit modal with all participant fields</li>
                <li class="success">✅ Backend handler for updating participant data</li>
                <li class="success">✅ Register tab added to admin navigation</li>
                <li class="success">✅ Auto-redirect to Register section when accessed from admin</li>
                <li class="success">✅ Geocoding for updated addresses</li>
                <li class="success">✅ Clear optimization when driver status changes</li>
            </ul>
        </div>

        <div class="test-section">
            <h2>How It Works:</h2>
            <ol>
                <li><strong>In Admin Dashboard:</strong> Click the blue edit button (pencil icon) on any participant card</li>
                <li><strong>Edit Modal Opens:</strong> Modify any participant information</li>
                <li><strong>Save Changes:</strong> Click "Save Changes" to update the database</li>
                <li><strong>Success Message:</strong> Shows confirmation and reloads page</li>
                <li><strong>Register from Admin:</strong> Click "Register" button in admin nav to add new participants</li>
            </ol>
        </div>
    </div>

    <script>
    async function testEdit() {
        const resultsDiv = document.getElementById('results');
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = 'Testing edit functionality...';

        // Test data for editing
        const testData = {
            id: 1, // Assuming user ID 1 exists
            name: "Sarah Johnson (Edited)",
            email: "sarah.edited@example.com",
            phone: "608-555-9999",
            address: "123 Updated St, Madison, WI 53703",
            willing_to_drive: true,
            vehicle_capacity: 5,
            vehicle_make: "Honda",
            vehicle_model: "Pilot",
            vehicle_color: "Blue",
            special_notes: "Updated via test script"
        };

        try {
            // First, we need to simulate admin login by setting session
            resultsDiv.innerHTML += '<br>Note: This test requires admin session. Please login to admin first.';

            // Simulate the edit request
            const response = await fetch('/admin/edit_participant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(testData)
            });

            const result = await response.json();

            if (result.success) {
                resultsDiv.innerHTML = `
                    <h3 class="success">✅ Edit Test Successful!</h3>
                    <p>Participant updated successfully.</p>
                    <p>Response: ${JSON.stringify(result, null, 2)}</p>
                `;
            } else {
                resultsDiv.innerHTML = `
                    <h3 class="error">❌ Edit Test Failed</h3>
                    <p>Error: ${result.message}</p>
                    <p>Response: ${JSON.stringify(result, null, 2)}</p>
                    <p>Note: You may need to login as admin first.</p>
                `;
            }
        } catch (error) {
            resultsDiv.innerHTML = `
                <h3 class="error">❌ Network Error</h3>
                <p>Error: ${error.toString()}</p>
            `;
        }
    }
    </script>
</body>
</html>