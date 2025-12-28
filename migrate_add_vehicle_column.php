<?php
require 'config.php';

try {
    // Add vehicle_make_model column to parts table
    $pdo->exec("ALTER TABLE parts ADD COLUMN vehicle_make_model VARCHAR(255) NULL DEFAULT NULL");

    // Add vehicle_make_model column to labors table
    $pdo->exec("ALTER TABLE labors ADD COLUMN vehicle_make_model VARCHAR(255) NULL DEFAULT NULL");

    echo "Successfully added vehicle_make_model column to parts and labors tables.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This might be because the column already exists.\n";
}
?>