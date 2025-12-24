<?php
require 'config.php';

echo "Testing oil functionality...\n";

try {
    // Check if oils column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM invoices LIKE 'oils'");
    $stmt->execute();
    $column = $stmt->fetch();

    if ($column) {
        echo "✓ Oils column exists in invoices table\n";
    } else {
        echo "✗ Oils column missing in invoices table\n";
    }

    // Check oil tables
    $tables = ['oil_brands', 'oil_viscosities', 'oil_prices'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "✓ $table table exists\n";
        } else {
            echo "✗ $table table missing\n";
        }
    }

    // Check sample data
    $stmt = $pdo->query("SELECT COUNT(*) FROM oil_brands");
    $brandCount = $stmt->fetchColumn();
    echo "Oil brands count: $brandCount\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM oil_viscosities");
    $viscosityCount = $stmt->fetchColumn();
    echo "Oil viscosities count: $viscosityCount\n";

    echo "Oil functionality test completed!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>