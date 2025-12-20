<?php
require 'config.php';
try {
    // Add vin column if missing
    $pdo->exec("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS vin VARCHAR(64) NULL");
    $pdo->exec("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS images TEXT NULL");
    echo "Migration completed: vin and images columns ensured.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
