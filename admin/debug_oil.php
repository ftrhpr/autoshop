<?php
// Debug script for oil management page
require '../config.php';

echo "<h1>Oil Management Debug</h1>";

try {
    echo "<p>✅ Database connection successful</p>";

    // Check if oil tables exist
    $tables = ['oil_brands', 'oil_viscosities', 'oil_prices'];
    $missingTables = [];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "<p>✅ Table `$table` exists ($count records)</p>";
        } catch (Exception $e) {
            echo "<p>❌ Table `$table` does not exist</p>";
            $missingTables[] = $table;
        }
    }

    if (!empty($missingTables)) {
        echo "<h2>❌ Missing Tables - Run setup_database.php</h2>";
        echo "<p>The following tables need to be created:</p>";
        echo "<ul>";
        foreach ($missingTables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        echo "<p><strong>Instructions:</strong></p>";
        echo "<ol>";
        echo "<li>Upload <code>setup_database.php</code> to your live server</li>";
        echo "<li>Access <code>https://new.otoexpress.ge/setup_database.php</code> in your browser</li>";
        echo "<li>The script will create the missing tables automatically</li>";
        echo "</ol>";
    } else {
        echo "<h2>✅ All tables exist - testing queries</h2>";

        // Test the queries used in labors_parts_pro.php
        try {
            $oilBrands = $pdo->query("SELECT * FROM oil_brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo "<p>✅ Oil brands query works (" . count($oilBrands) . " brands)</p>";
        } catch (Exception $e) {
            echo "<p>❌ Oil brands query failed: " . $e->getMessage() . "</p>";
        }

        try {
            $oilViscosities = $pdo->query("SELECT * FROM oil_viscosities ORDER BY viscosity")->fetchAll(PDO::FETCH_ASSOC);
            echo "<p>✅ Oil viscosities query works (" . count($oilViscosities) . " viscosities)</p>";
        } catch (Exception $e) {
            echo "<p>❌ Oil viscosities query failed: " . $e->getMessage() . "</p>";
        }

        try {
            $oilPrices = $pdo->query("
                SELECT op.*, ob.name as brand_name, ov.viscosity as viscosity_name, ov.description as viscosity_description
                FROM oil_prices op
                JOIN oil_brands ob ON op.brand_id = ob.id
                JOIN oil_viscosities ov ON op.viscosity_id = ov.id
                ORDER BY ob.name, ov.viscosity, op.package_type
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo "<p>✅ Oil prices query works (" . count($oilPrices) . " prices)</p>";
        } catch (Exception $e) {
            echo "<p>❌ Oil prices query failed: " . $e->getMessage() . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Check your database credentials in config.php</p>";
}
?>