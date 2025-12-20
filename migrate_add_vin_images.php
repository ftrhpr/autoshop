<?php
require 'config.php';
try {
    $table = 'invoices';
    $columns = [
        'vin' => 'VARCHAR(64) NULL',
        'images' => 'TEXT NULL'
    ];

    foreach ($columns as $col => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $col]);
        $exists = (int)$stmt->fetchColumn();
        if (!$exists) {
            $sql = "ALTER TABLE `" . $table . "` ADD COLUMN `$col` $definition";
            $pdo->exec($sql);
            echo "Added column: $col\n";
        } else {
            echo "Column already exists: $col\n";
        }
    }

    echo "Migration finished.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
