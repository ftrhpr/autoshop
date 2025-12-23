<?php
require 'config.php';

try {
    $tables = ['labors', 'parts'];
    foreach ($tables as $t) {
        // Check INFORMATION_SCHEMA for existing column (works on older MySQL versions)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$dbname, $t, 'vehicle_make_model']);
        $exists = (bool)$stmt->fetchColumn();
        if ($exists) {
            echo "Column 'vehicle_make_model' already exists on table {$t}.\n";
        } else {
            $pdo->exec("ALTER TABLE `{$t}` ADD COLUMN `vehicle_make_model` VARCHAR(255) DEFAULT NULL AFTER `description`");
            echo "Added column 'vehicle_make_model' to table {$t}.\n";
        }
    }
    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>