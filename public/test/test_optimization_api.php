<?php
// Test optimization API endpoint
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin login

// Prepare the request data
$data = [
    'event_id' => 1,
    'target_vehicles' => 4,
    'max_drive_time' => 50
];

// Create a context for the HTTP request
$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

// Simulate the POST request by setting up the environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Set the input stream
$input = json_encode($data);
$tempInput = fopen('php://temp', 'r+');
fwrite($tempInput, $input);
rewind($tempInput);

// Save the current directory
$originalDir = getcwd();

// Change to admin directory (where optimize_enhanced.php expects to be)
chdir('/var/www/partycarpool.clodhost.com/public/admin');

// Capture output
ob_start();

// Override file_get_contents for php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    private $position = 0;
    private static $input;

    public static function setInput($input) {
        self::$input = $input;
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($count) {
        $ret = substr(self::$input, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen(self::$input);
    }

    public function stream_stat() {
        return [];
    }

    public function stream_tell() {
        return $this->position;
    }
}

MockPhpStream::setInput($input);

// Include and run the optimization
try {
    include 'optimize_enhanced.php';
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . PHP_EOL;
}

// Get the output
$output = ob_get_clean();

// Restore stream wrapper
stream_wrapper_restore("php");

// Restore directory
chdir($originalDir);

// Parse and display results
echo "=== OPTIMIZATION API TEST ===" . PHP_EOL;
echo "Request: " . json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
echo PHP_EOL . "Response:" . PHP_EOL;

$result = json_decode($output, true);
if ($result) {
    if ($result['success']) {
        echo "✅ Optimization successful!" . PHP_EOL;
        echo "Vehicles used: " . $result['vehicles_needed'] . PHP_EOL;
        echo "Routes created: " . count($result['routes']) . PHP_EOL;
    } else {
        echo "❌ Optimization failed!" . PHP_EOL;
        echo "Error: " . $result['message'] . PHP_EOL;
    }
    echo PHP_EOL . "Full response:" . PHP_EOL;
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo "❌ Invalid JSON response!" . PHP_EOL;
    echo "Raw output: " . $output . PHP_EOL;
}
?>