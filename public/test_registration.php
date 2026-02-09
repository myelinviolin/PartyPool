<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Registration</title>
</head>
<body>
    <h1>Test Registration</h1>
    <button onclick="testRegistration()">Test Registration API</button>
    <div id="result"></div>

    <script>
    async function testRegistration() {
        const formData = {
            name: "Test User " + Date.now(),
            email: "test" + Date.now() + "@example.com",
            phone: "555-1234",
            willing_to_drive: true,
            address: "123 Test St, Madison, WI 53703",
            lat: 43.0731,
            lng: -89.4012,
            event_id: 1,
            special_notes: "Test registration",
            vehicle_capacity: 4,
            vehicle_make: "Toyota",
            vehicle_model: "Camry",
            vehicle_color: "Blue"
        };

        console.log("Sending data:", formData);

        try {
            const response = await fetch('/api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const text = await response.text();
            console.log("Response status:", response.status);
            console.log("Response text:", text);

            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                result = { error: "Invalid JSON response", rawText: text };
            }

            document.getElementById('result').innerHTML = `
                <h2>Response Status: ${response.status}</h2>
                <pre>${JSON.stringify(result, null, 2)}</pre>
                <h3>Raw Response:</h3>
                <pre>${text}</pre>
            `;

            if (response.ok) {
                alert('Registration successful! User ID: ' + result.user_id);
            } else {
                alert('Registration failed: ' + (result.message || text));
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = `
                <h2>Error</h2>
                <pre>${error.toString()}</pre>
            `;
            alert('Error during registration: ' + error);
        }
    }
    </script>
</body>
</html>