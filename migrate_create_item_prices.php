<?php
require 'config.php';

try {
    // Create item_prices table to store vehicle-specific prices for parts and labors
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'item_prices'");
    $stmt->execute([$dbname]);
    $exists = (bool)$stmt->fetchColumn();
    if ($exists) {
        echo "Table 'item_prices' already exists.\n";
        exit(0);
    }

    $sql = "CREATE TABLE `item_prices` (
        `id` INT unsigned NOT NULL AUTO_INCREMENT,
        `item_type` VARCHAR(10) NOT NULL,
        `item_id` INT unsigned NOT NULL,
        `vehicle_make_model` VARCHAR(255) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `created_by` INT unsigned DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_item_vehicle` (`item_type`,`item_id`,`vehicle_make_model`),
        KEY `idx_item` (`item_type`,`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "Created table 'item_prices'.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>