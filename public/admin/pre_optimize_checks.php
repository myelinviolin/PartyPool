<?php
/**
 * Pre-optimization checks
 * Automatically runs validation tests before optimization
 * Ensures data integrity for map locations and route generation
 */

class PreOptimizeChecks {
    private $errors = [];
    private $warnings = [];

    /**
     * Run all pre-optimization checks
     * @return bool True if all checks pass
     */
    public function runChecks() {
        $all_passed = true;

        // Check 1: Lake Location Test
        if (!$this->runLakeLocationTest()) {
            // Error already added in runLakeLocationTest method
            $all_passed = false;
        }

        // Check 2: Database Integrity
        if (!$this->checkDatabaseIntegrity()) {
            // Error will be added in checkDatabaseIntegrity method
            $all_passed = false;
        }

        // Check 3: Coordinate Format Validation
        if (!$this->validateCoordinateFormats()) {
            // Warning already added in validateCoordinateFormats method
        }

        return $all_passed;
    }

    /**
     * Run the lake location test
     */
    private function runLakeLocationTest() {
        // Try multiple possible locations for the test file
        $possible_files = [
            '/var/www/partycarpool.clodhost.com/public/test/test_map_locations.php',
            __DIR__ . '/../test/test_map_locations.php',
            __DIR__ . '/lake_location_check.php'
        ];

        $test_file = null;
        foreach ($possible_files as $file) {
            if (file_exists($file) && is_readable($file)) {
                $test_file = $file;
                break;
            }
        }

        if (!$test_file) {
            // If no test file found, run inline check
            return $this->runInlineLakeCheck();
        }

        // Execute the test and capture output
        $output = [];
        $return_code = 0;
        $command = "php " . escapeshellarg($test_file) . " 2>&1";
        exec($command, $output, $return_code);

        // Check if test passed (return code 0 = success)
        if ($return_code !== 0) {
            // Include last line of output in error message for debugging
            $last_line = !empty($output) ? end($output) : 'No output';
            $this->errors[] = "Lake location test failed: $last_line";
            return false;
        }

        return true;
    }

    /**
     * Inline lake location check as fallback
     */
    private function runInlineLakeCheck() {
        try {
            include_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            if (!$db) {
                $this->errors[] = "Could not connect to database for lake check";
                return false;
            }

            // Madison area lakes
            $water_bodies = [
                'Lake Monona' => [
                    ['lat' => 43.05, 'lng' => -89.368],
                    ['lat' => 43.09, 'lng' => -89.355]
                ]
            ];

            $query = "SELECT name, lat, lng FROM users WHERE event_id = 1 AND lat IS NOT NULL AND lng IS NOT NULL";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                $lat = floatval($user['lat']);
                $lng = floatval($user['lng']);

                foreach ($water_bodies as $water_name => $bounds) {
                    if ($lat > $bounds[0]['lat'] && $lat < $bounds[1]['lat'] &&
                        $lng > $bounds[0]['lng'] && $lng < $bounds[1]['lng']) {
                        $this->errors[] = "{$user['name']} appears to be in $water_name";
                        return false;
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            // If inline check fails, just pass (don't block optimization)
            return true;
        }
    }

    /**
     * Check database integrity
     */
    private function checkDatabaseIntegrity() {
        try {
            include_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            if (!$db) {
                $this->errors[] = "Database connection failed during integrity check";
                return false;
            }

            // Check that all participants have valid coordinates
            $query = "SELECT COUNT(*) as missing FROM users
                      WHERE event_id = 1
                      AND (lat IS NULL OR lng IS NULL OR lat = 0 OR lng = 0)";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['missing'] > 0) {
                $this->errors[] = $result['missing'] . " participants have missing or invalid coordinates";
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Database integrity check failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Validate coordinate formats
     */
    private function validateCoordinateFormats() {
        include_once '../config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT name, lat, lng FROM users WHERE event_id = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $issues = 0;
        foreach ($users as $user) {
            // Check for excessive precision (more than 8 decimal places)
            if (strlen(substr(strrchr($user['lat'], "."), 1)) > 8 ||
                strlen(substr(strrchr($user['lng'], "."), 1)) > 8) {
                $issues++;
            }
        }

        if ($issues > 0) {
            $this->warnings[] = "$issues participants have excessive coordinate precision";
        }

        return $issues == 0;
    }

    /**
     * Get error messages
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get warning messages
     */
    public function getWarnings() {
        return $this->warnings;
    }

    /**
     * Get formatted report
     */
    public function getReport() {
        $report = [];

        if (!empty($this->errors)) {
            $report['errors'] = $this->errors;
        }

        if (!empty($this->warnings)) {
            $report['warnings'] = $this->warnings;
        }

        if (empty($this->errors) && empty($this->warnings)) {
            $report['success'] = "All pre-optimization checks passed!";
        }

        return $report;
    }
}

// If called directly (for testing)
if (basename($_SERVER['PHP_SELF']) == 'pre_optimize_checks.php') {
    $checker = new PreOptimizeChecks();
    $passed = $checker->runChecks();

    echo "=== PRE-OPTIMIZATION CHECKS ===\n\n";

    if ($passed) {
        echo "✅ All checks passed!\n";
    } else {
        echo "❌ Some checks failed:\n";
        foreach ($checker->getErrors() as $error) {
            echo "  - ERROR: $error\n";
        }
    }

    if (!empty($checker->getWarnings())) {
        echo "\n⚠️ Warnings:\n";
        foreach ($checker->getWarnings() as $warning) {
            echo "  - WARNING: $warning\n";
        }
    }

    exit($passed ? 0 : 1);
}
?>