<?php
// Test script to check vehicle API endpoints
require 'config.php';

echo "Testing vehicle database...\n";

try {
    // Check vehicle_makes table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vehicle_makes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Vehicle makes count: " . $result['count'] . "\n";

    // Check vehicle_models table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vehicle_models");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Vehicle models count: " . $result['count'] . "\n";

    // Test API endpoints
    echo "\nTesting API endpoints...\n";

    // Test makes API
    $makesUrl = 'http://localhost:8000/admin/api_vehicle_makes.php';
    echo "Makes API URL: $makesUrl\n";

    // Test models API (need a make_id)
    $stmt = $pdo->query("SELECT id, name FROM vehicle_makes LIMIT 1");
    $make = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($make) {
        $modelsUrl = 'http://localhost:8000/admin/api_vehicle_models.php?make_id=' . $make['id'];
        echo "Models API URL: $modelsUrl (for make: {$make['name']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>