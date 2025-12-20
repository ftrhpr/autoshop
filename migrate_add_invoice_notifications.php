<?php
require 'config.php';

echo "Running migration: add invoice_notifications table...\n";

$exists = $pdo->query("SHOW TABLES LIKE 'invoice_notifications'")->fetch();
if ($exists) {
    echo "- invoice_notifications already exists.\n";
    exit;
}

$sql = "CREATE TABLE invoice_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seen_at DATETIME NULL,
    UNIQUE KEY invoice_user (invoice_id, user_id),
    INDEX (invoice_id),
    INDEX (user_id),
    CONSTRAINT fk_notify_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_notify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$pdo->exec($sql);

echo "- migration completed.\n";