<?php
require 'config.php';
try {
    $table = 'item_price_usage';

    // Check if table exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $exists = (int)$stmt->fetchColumn();

    if (!$exists) {
        $sql = "CREATE TABLE `" . $table . "` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_type` VARCHAR(10) NOT NULL,
            `item_id` INT NOT NULL,
            `vehicle_make_model` VARCHAR(255) DEFAULT NULL,
            `price` DECIMAL(12,2) NOT NULL,
            `usage_count` INT NOT NULL DEFAULT 0,
            `last_used_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY u_item_vehicle_price (item_type, item_id, vehicle_make_model, price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
        echo "Created table: $table\n";
    } else {
        echo "Table already exists: $table\n";
    }

    echo "Migration finished.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>