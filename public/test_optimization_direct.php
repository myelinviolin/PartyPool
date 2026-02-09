<?php
// Directly test the optimization algorithm without database

echo "Testing 2-opt pickup order optimization\n";
echo "========================================\n\n";

// Simulate Robert Taylor's scenario
$driver = [
    'lat' => 40.7589,
    'lng' => -73.9851,
    'name' => 'Robert Taylor (Center)'
];

$passengers = [
    ['name' => 'David Wilson', 'lat' => 40.7749, 'lng' => -73.9703], // Northeast
    ['name' => 'Jennifer Lee', 'lat' => 40.7489, 'lng' => -74.0000], // Southwest
    ['name' => 'Kevin Martinez', 'lat' => 40.7650, 'lng' => -73.9700]  // East
];

$event = [
    'lat' => 40.7700,
    'lng' => -73.9800
];

echo "Driver: Robert Taylor at (" . $driver['lat'] . ", " . $driver['lng'] . ")\n";
echo "Event location: (" . $event['lat'] . ", " . $event['lng'] . ")\n\n";

echo "Passengers (unoptimized order):\n";
foreach ($passengers as $i => $p) {
    echo "  " . ($i + 1) . ". " . $p['name'] . " at (" . $p['lat'] . ", " . $p['lng'] . ")\n";
}

// Calculate distance for unoptimized order
echo "\n--- Unoptimized Route (Original Order) ---\n";
$unoptimized_distance = calculateRouteDistance($driver, $passengers, $event);
echo "Total distance: " . round($unoptimized_distance, 2) . " miles\n\n";

// Now optimize using 2-opt
echo "--- Optimizing with 2-opt Algorithm ---\n";
$optimized_order = optimizePickupOrder($driver, $passengers, $event);

echo "Optimized pickup order:\n";
$optimized_passengers = [];
foreach ($optimized_order as $index) {
    $optimized_passengers[] = $passengers[$index];
    echo "  " . (count($optimized_passengers)) . ". " . $passengers[$index]['name'] . "\n";
}

// Calculate distance for optimized order
echo "\n--- Optimized Route ---\n";
$optimized_distance = calculateRouteDistance($driver, $optimized_passengers, $event);
echo "Total distance: " . round($optimized_distance, 2) . " miles\n";

echo "\nSavings: " . round($unoptimized_distance - $optimized_distance, 2) . " miles (";
echo round((1 - $optimized_distance/$unoptimized_distance) * 100, 1) . "% improvement)\n";

function optimizePickupOrder($driver_location, $passengers, $event_location) {
    if (count($passengers) <= 1) {
        return array_keys($passengers);
    }

    // Create full location array: driver -> passengers -> event
    $locations = [];
    $locations[] = $driver_location;
    foreach ($passengers as $p) {
        $locations[] = $p;
    }
    $locations[] = $event_location;

    $n = count($locations);
    $best_distance = calculateTotalDistance($locations);
    $improved = true;

    while ($improved) {
        $improved = false;

        // Try all possible 2-opt swaps (only for passenger portion)
        for ($i = 1; $i < $n - 2; $i++) {
            for ($j = $i + 1; $j < $n - 1; $j++) {
                // Perform 2-opt swap
                $new_locations = perform2OptSwap($locations, $i, $j);
                $new_distance = calculateTotalDistance($new_locations);

                if ($new_distance < $best_distance - 0.01) { // Small epsilon for float comparison
                    $locations = $new_locations;
                    $best_distance = $new_distance;
                    $improved = true;
                    echo "  Improved by swapping positions $i and $j: " . round($new_distance, 2) . " miles\n";
                }
            }
        }
    }

    // Extract passenger order from optimized locations
    $best_order = [];
    for ($i = 1; $i < count($locations) - 1; $i++) {
        // Find which passenger this is
        for ($p = 0; $p < count($passengers); $p++) {
            if ($locations[$i]['lat'] == $passengers[$p]['lat'] &&
                $locations[$i]['lng'] == $passengers[$p]['lng']) {
                $best_order[] = $p;
                break;
            }
        }
    }

    return $best_order;
}

function perform2OptSwap($locations, $i, $j) {
    $new_locations = array_slice($locations, 0, $i);
    $reversed = array_reverse(array_slice($locations, $i, $j - $i + 1));
    $new_locations = array_merge($new_locations, $reversed);
    $new_locations = array_merge($new_locations, array_slice($locations, $j + 1));
    return $new_locations;
}

function calculateTotalDistance($locations) {
    $total = 0;
    for ($i = 0; $i < count($locations) - 1; $i++) {
        $total += calculateDistance(
            $locations[$i]['lat'], $locations[$i]['lng'],
            $locations[$i + 1]['lat'], $locations[$i + 1]['lng']
        );
    }
    return $total;
}

function calculateRouteDistance($driver, $passengers, $event) {
    $locations = [$driver];
    foreach ($passengers as $p) {
        $locations[] = $p;
    }
    $locations[] = $event;

    $total = 0;
    for ($i = 0; $i < count($locations) - 1; $i++) {
        $dist = calculateDistance(
            $locations[$i]['lat'], $locations[$i]['lng'],
            $locations[$i + 1]['lat'], $locations[$i + 1]['lng']
        );
        echo "  Leg " . ($i + 1) . ": ";
        if ($i == 0) {
            echo "Robert -> ";
        } elseif ($i < count($passengers)) {
            echo $passengers[$i - 1]['name'] . " -> ";
        }

        if ($i == count($passengers)) {
            echo "Event";
        } elseif ($i < count($passengers)) {
            echo $passengers[$i]['name'];
        }
        echo " = " . round($dist, 2) . " miles\n";
        $total += $dist;
    }
    return $total;
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 3959; // miles
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);

    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLng / 2) * sin($deltaLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}