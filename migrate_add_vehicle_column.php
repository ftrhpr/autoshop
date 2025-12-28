<?php
require 'config.php';

try {
    // Check if vehicle_make_model column exists in parts table
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts' AND COLUMN_NAME = 'vehicle_make_model'");
    $exists_parts = $stmt->fetchColumn() > 0;

    if (!$exists_parts) {
        $pdo->exec("ALTER TABLE parts ADD COLUMN vehicle_make_model VARCHAR(255) NULL DEFAULT NULL");
        echo "Added vehicle_make_model column to parts table.\n";
    } else {
        echo "vehicle_make_model column already exists in parts table.\n";
    }

    // Check if vehicle_make_model column exists in labors table
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'labors' AND COLUMN_NAME = 'vehicle_make_model'");
    $exists_labors = $stmt->fetchColumn() > 0;

    if (!$exists_labors) {
        $pdo->exec("ALTER TABLE labors ADD COLUMN vehicle_make_model VARCHAR(255) NULL DEFAULT NULL");
        echo "Added vehicle_make_model column to labors table.\n";
    } else {
        echo "vehicle_make_model column already exists in labors table.\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
?>