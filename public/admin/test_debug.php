<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
require_once 'optimize_enhanced.php';

$database = new Database();
$db = $database->getConnection();

// Create optimizer and run
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

echo "OPTIMIZATION DEBUG OUTPUT\n";
echo "========================\n\n";

if (isset($result['success'])) {
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
}

if (isset($result['message'])) {
    echo "Message: " . $result['message'] . "\n";
}

if (isset($result['duplicates']) && count($result['duplicates']) > 0) {
    echo "\nDUPLICATE ASSIGNMENTS:\n";
    foreach ($result['duplicates'] as $id => $count) {
        echo "  - User ID $id assigned $count times\n";
    }
}

if (isset($result['unassigned']) && count($result['unassigned']) > 0) {
    echo "\nUNASSIGNED PARTICIPANTS:\n";
    foreach ($result['unassigned'] as $p) {
        echo "  - " . $p['name'] . " (ID: " . $p['id'] . ")\n";
    }
}

if (isset($result['routes']) && count($result['routes']) > 0) {
    echo "\nROUTES (even with errors):\n";
    foreach ($result['routes'] as $i => $route) {
        echo "\nRoute " . ($i + 1) . ":\n";
        echo "  Driver: " . $route['driver_name'] . " (ID: " . $route['driver_id'] . ")\n";
        if (isset($route['passengers']) && count($route['passengers']) > 0) {
            echo "  Passengers:\n";
            foreach ($route['passengers'] as $p) {
                echo "    - " . $p['name'] . " (ID: " . $p['id'] . ")\n";
            }
        } else {
            echo "  No passengers\n";
        }
    }

    // Check for duplicates manually
    echo "\n\nMANUAL DUPLICATE CHECK:\n";
    $all_ids = [];
    foreach ($result['routes'] as $route) {
        $all_ids[] = $route['driver_id'];
        if (isset($route['passengers'])) {
            foreach ($route['passengers'] as $p) {
                $all_ids[] = $p['id'];
            }
        }
    }

    $counts = array_count_values($all_ids);
    $has_duplicates = false;
    foreach ($counts as $id => $count) {
        if ($count > 1) {
            $has_duplicates = true;

            // Find the name
            $name = '';
            foreach ($result['routes'] as $route) {
                if ($route['driver_id'] == $id) {
                    $name = $route['driver_name'];
                    break;
                }
                foreach ($route['passengers'] ?? [] as $p) {
                    if ($p['id'] == $id) {
                        $name = $p['name'];
                        break 2;
                    }
                }
            }

            echo "  - ID $id ($name) appears $count times\n";

            // Show where they appear
            foreach ($result['routes'] as $i => $route) {
                if ($route['driver_id'] == $id) {
                    echo "    * As driver in Route " . ($i + 1) . "\n";
                }
                foreach ($route['passengers'] ?? [] as $p) {
                    if ($p['id'] == $id) {
                        echo "    * As passenger in Route " . ($i + 1) . "\n";
                    }
                }
            }
        }
    }

    if (!$has_duplicates) {
        echo "  No duplicates found!\n";
    }
}

echo "\n";
?>