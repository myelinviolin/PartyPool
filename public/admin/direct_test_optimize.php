<?php
// Direct CLI test of optimization
$_SESSION = ['admin_id' => 1];

// Mock php://input
$mock_input = json_encode([
    'event_id' => 1,
    'target_vehicles' => null,
    'max_drive_time' => 50
]);

// Replace php://input stream
stream_wrapper_unregister("php");
stream_wrapper_register("php", "TestStream");

class TestStream {
    private $data;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path) {
        global $mock_input;
        if ($path === 'php://input') {
            $this->data = $mock_input;
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

    public function stream_tell() {
        return $this->position;
    }

    public function stream_seek($offset, $whence) {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = strlen($this->data) + $offset;
                break;
        }
        return true;
    }
}

// Capture output
ob_start();

try {
    require 'optimize_enhanced.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}

$output = ob_get_clean();

// Restore stream wrapper
stream_wrapper_restore("php");

// Display results
echo "Output from optimize_enhanced.php:\n";
echo "=====================================\n";
echo $output;
echo "\n=====================================\n";

// Try to parse as JSON
$json = json_decode($output);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "\n✅ Valid JSON output\n";
    echo "Result summary:\n";
    echo "- Success: " . ($json->success ? 'Yes' : 'No') . "\n";
    if (isset($json->message)) {
        echo "- Message: " . $json->message . "\n";
    }
} else {
    echo "\n❌ Invalid JSON output\n";
    echo "Error: " . json_last_error_msg() . "\n";
}
?>