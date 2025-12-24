<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test database connection
require 'config.php';

echo "<h1>Database Connection Test</h1>";

try {
    echo "<p>✅ Database connection successful!</p>";

    // Test basic queries
    $stmt = $pdo->query("SELECT 1");
    echo "<p>✅ Basic query works</p>";

    // Check if oil tables exist
    $tables = ['oil_brands', 'oil_viscosities', 'oil_prices'];
    echo "<h2>Oil Tables Check:</h2>";
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        if ($exists) {
            echo "<p>✅ Table $table exists</p>";
        } else {
            echo "<p>❌ Table $table does NOT exist</p>";
        }
    }

    // Check if invoices table has oils column
    echo "<h2>Invoices Table Check:</h2>";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM invoices LIKE 'oils'");
    $stmt->execute();
    $column = $stmt->fetch();
    if ($column) {
        echo "<p>✅ invoices.oils column exists</p>";
    } else {
        echo "<p>❌ invoices.oils column does NOT exist</p>";
    }

    // Test oil data queries
    echo "<h2>Oil Data Test:</h2>";
    try {
        $oilBrands = $pdo->query('SELECT * FROM oil_brands ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>✅ Oil brands query works (" . count($oilBrands) . " brands found)</p>";
    } catch (Exception $e) {
        echo "<p>❌ Oil brands query failed: " . $e->getMessage() . "</p>";
    }

    try {
        $oilViscosities = $pdo->query('SELECT * FROM oil_viscosities ORDER BY viscosity')->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>✅ Oil viscosities query works (" . count($oilViscosities) . " viscosities found)</p>";
    } catch (Exception $e) {
        echo "<p>❌ Oil viscosities query failed: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Check your database credentials in config.php</p>";
}
?>