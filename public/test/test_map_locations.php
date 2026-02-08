<?php
/**
 * Test to verify all participant map markers are placed at valid locations
 * Ensures no one appears in lakes, rivers, or other invalid coordinates
 */

include_once '../config/database.php';

class MapLocationTest {
    private $db;
    private $errors = [];
    private $warnings = [];
    private $passed = 0;

    // Madison area lakes and water bodies to check
    private $water_bodies = [
        'Lake Mendota' => [
            'bounds' => [
                ['lat' => 43.10, 'lng' => -89.48],
                ['lat' => 43.15, 'lng' => -89.36]
            ]
        ],
        'Lake Monona' => [
            'bounds' => [
                ['lat' => 43.05, 'lng' => -89.368],
                ['lat' => 43.09, 'lng' => -89.355]
            ]
        ],
        'Lake Waubesa' => [
            'bounds' => [
                ['lat' => 42.99, 'lng' => -89.34],
                ['lat' => 43.02, 'lng' => -89.31]
            ]
        ]
    ];

    // Valid coordinate ranges for Madison area
    private $madison_bounds = [
        'min_lat' => 42.95,
        'max_lat' => 43.20,
        'min_lng' => -89.60,
        'max_lng' => -89.25
    ];

    public function __construct($db) {
        $this->db = $db;
    }

    public function run() {
        echo "=== MAP LOCATION VERIFICATION TEST ===\n";
        echo date('Y-m-d H:i:s') . "\n\n";

        $this->testAllParticipantLocations();
        $this->testEventLocation();
        $this->testCoordinatePrecision();
        $this->printResults();

        return count($this->errors) == 0;
    }

    private function testAllParticipantLocations() {
        echo "Testing participant locations...\n";

        $query = "SELECT id, name, address, lat, lng FROM users WHERE event_id = 1 ORDER BY id";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            $this->validateLocation($user);
        }
    }

    private function validateLocation($user) {
        $lat = floatval($user['lat']);
        $lng = floatval($user['lng']);
        $name = $user['name'];

        // Test 1: Has coordinates
        if (empty($user['lat']) || empty($user['lng'])) {
            $this->errors[] = "$name has no coordinates";
            return;
        }

        // Test 2: Within Madison area
        if ($lat < $this->madison_bounds['min_lat'] || $lat > $this->madison_bounds['max_lat'] ||
            $lng < $this->madison_bounds['min_lng'] || $lng > $this->madison_bounds['max_lng']) {
            $this->errors[] = "$name coordinates ($lat, $lng) are outside Madison area";
            return;
        }

        // Test 3: Not in any water body
        foreach ($this->water_bodies as $water_name => $water) {
            $bounds = $water['bounds'];
            if ($lat > $bounds[0]['lat'] && $lat < $bounds[1]['lat'] &&
                $lng > $bounds[0]['lng'] && $lng < $bounds[1]['lng']) {
                $this->errors[] = "$name appears to be in $water_name at ($lat, $lng)";
                return;
            }
        }

        // Test 4: Address matches general area (rough check)
        if (strpos($user['address'], 'Park St') !== false && $lng < -89.42) {
            $this->warnings[] = "$name on Park St has longitude $lng (seems too far west)";
        }
        if (strpos($user['address'], 'State St') !== false && abs($lng + 89.40) > 0.02) {
            $this->warnings[] = "$name on State St has unexpected longitude $lng";
        }

        $this->passed++;
    }

    private function testEventLocation() {
        echo "Testing event location...\n";

        $query = "SELECT event_name, event_address, event_lat, event_lng FROM events WHERE id = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            $lat = floatval($event['event_lat']);
            $lng = floatval($event['event_lng']);

            // Madison Capitol area check (common event location)
            if (strpos($event['event_name'], 'Capitol') !== false ||
                strpos($event['event_address'], 'Capitol') !== false) {
                $expected_lat = 43.074;
                $expected_lng = -89.384;

                if (abs($lat - $expected_lat) > 0.01 || abs($lng - $expected_lng) > 0.01) {
                    $this->warnings[] = "Event at Capitol has coordinates ($lat, $lng), expected near ($expected_lat, $expected_lng)";
                } else {
                    $this->passed++;
                }
            } else {
                // Just verify it's in Madison area
                if ($lat > $this->madison_bounds['min_lat'] && $lat < $this->madison_bounds['max_lat'] &&
                    $lng > $this->madison_bounds['min_lng'] && $lng < $this->madison_bounds['max_lng']) {
                    $this->passed++;
                } else {
                    $this->errors[] = "Event location outside Madison area";
                }
            }
        }
    }

    private function testCoordinatePrecision() {
        echo "Testing coordinate precision...\n";

        $query = "SELECT name, lat, lng FROM users WHERE event_id = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            // Check for reasonable precision (not too many decimal places)
            if (strlen(substr(strrchr($user['lat'], "."), 1)) > 8) {
                $this->warnings[] = "{$user['name']} has excessive latitude precision: {$user['lat']}";
            }
            if (strlen(substr(strrchr($user['lng'], "."), 1)) > 8) {
                $this->warnings[] = "{$user['name']} has excessive longitude precision: {$user['lng']}";
            }
        }
    }

    private function printResults() {
        echo "\n=== TEST RESULTS ===\n";
        echo "Passed: $this->passed checks\n";

        if (count($this->errors) > 0) {
            echo "\n❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        if (count($this->warnings) > 0) {
            echo "\n⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "  - $warning\n";
            }
        }

        if (count($this->errors) == 0) {
            echo "\n✅ ALL CRITICAL TESTS PASSED!\n";
            echo "All participant markers are at valid locations.\n";
            echo "No one is in lakes or other water bodies.\n";
        } else {
            echo "\n❌ TEST FAILED!\n";
            echo "Some participants have invalid map locations that need fixing.\n";
        }
    }
}

// Run the test
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "Error: Could not connect to database\n";
    exit(1);
}

$test = new MapLocationTest($db);
$success = $test->run();

// Exit with appropriate code for CI/CD integration
exit($success ? 0 : 1);
?>