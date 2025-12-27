<?php
// Migration to add supplier column to part_pricing_requests table
require_once 'config.php';

try {
    // Add supplier column to part_pricing_requests table
    $pdo->exec("ALTER TABLE part_pricing_requests ADD COLUMN supplier VARCHAR(255) NULL AFTER notes");

    echo "Supplier column added to part_pricing_requests table successfully." . PHP_EOL;

} catch (Exception $e) {
    echo "Error adding supplier column: " . $e->getMessage() . PHP_EOL;
}
?>