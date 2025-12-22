<?php
require 'config.php';

// Add labors and parts tables
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS labors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            default_price DECIMAL(10,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (name)
        );

        CREATE TABLE IF NOT EXISTS parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            default_price DECIMAL(10,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (name)
        );
    ");

    echo "Tables created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>