<?php
session_start();
$_SESSION['admin_id'] = 1; // Set admin session for testing

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper headers
header('Content-Type: text/plain');

echo "Testing optimize_enhanced.php directly...\n";
echo "=====================================\n\n";

// Prepare test data
$test_data = json_encode([
    'event_id' => 1,
    'target_vehicles' => null,
    'max_drive_time' => 50
]);

echo "Input JSON: " . $test_data . "\n\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Create a temporary file to store the JSON data
$temp = tmpfile();
fwrite($temp, $test_data);
fseek($temp, 0);

// Replace php://input with our temp file
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    public $context;
    private $data;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            global $test_data;
            $this->data = $test_data;
            return true;
        }
        return false;
    }

    public function stream_read($count) {
        $result = substr($this->data, $this->position, $count);
        $this->position += strlen($result);
        return $result;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat() {
        return [];
    }
}

// Capture output
ob_start();

try {
    // Include the file
    include_once '../config/database.php';

    // Check database connection first
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "Database connection successful\n\n";

        // Now test the actual optimization
        echo "Running optimization...\n";
        echo "Raw output from optimize_enhanced.php:\n";
        echo "---------------------------------------\n";

        // Reset output buffer for clean capture
        ob_end_flush();
        ob_start();

        // Include the optimization script
        include 'optimize_enhanced.php';

        $output = ob_get_clean();
        echo $output . "\n";
        echo "---------------------------------------\n\n";

        // Try to decode as JSON
        $json = json_decode($output);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Valid JSON returned!\n";
            echo "Formatted output:\n";
            print_r($json);
        } else {
            echo "❌ Invalid JSON returned!\n";
            echo "JSON Error: " . json_last_error_msg() . "\n";
            echo "Raw output length: " . strlen($output) . " bytes\n";

            // Check for PHP errors in output
            if (strpos($output, 'Warning') !== false || strpos($output, 'Notice') !== false || strpos($output, 'Fatal error') !== false) {
                echo "\n⚠️ PHP errors detected in output!\n";
            }
        }
    } else {
        echo "❌ Database connection failed!\n";
    }

} catch (Exception $e) {
    $error_output = ob_get_clean();
    echo "❌ Exception caught: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    if ($error_output) {
        echo "\nOutput before error:\n" . $error_output . "\n";
    }
}

// Clean up
stream_wrapper_restore("php");
?>