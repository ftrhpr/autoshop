<?php
// Run this script once to create the 'messages' table used for inbox functionality.
require_once 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        body TEXT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    echo "messages table created or already exists." . PHP_EOL;
} catch (PDOException $e) {
    echo "Error creating messages table: " . $e->getMessage() . PHP_EOL;
}
