<?php
require 'config.php';
try {
    $table = 'invoices';
    $column = 'opened_in_fina';

    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $exists = (int)$stmt->fetchColumn();

    if (!$exists) {
        $sql = "ALTER TABLE `" . $table . "` ADD COLUMN `$column` TINYINT(1) NOT NULL DEFAULT 0";
        $pdo->exec($sql);
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