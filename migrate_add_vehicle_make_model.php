<?php
require 'config.php';

try {
    $pdo->exec("ALTER TABLE labors ADD COLUMN IF NOT EXISTS vehicle_make_model VARCHAR(255) DEFAULT NULL AFTER description;");
    $pdo->exec("ALTER TABLE parts ADD COLUMN IF NOT EXISTS vehicle_make_model VARCHAR(255) DEFAULT NULL AFTER description;");
    echo "Columns added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>