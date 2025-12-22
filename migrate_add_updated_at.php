<?php
require 'config.php';
try {
    $table = 'invoices';
    $column = 'updated_at';

    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $exists = (int)$stmt->fetchColumn();

    if (!$exists) {
        $sql = "ALTER TABLE `" . $table . "` ADD COLUMN `$column` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        $pdo->exec($sql);
        // Also set existing to created_at
        $pdo->exec("UPDATE `$table` SET `$column` = `created_at` WHERE `$column` IS NULL");
        echo "Added column: $column\n";
    } else {
        echo "Column already exists: $column\n";
    }

    echo "Migration finished.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>