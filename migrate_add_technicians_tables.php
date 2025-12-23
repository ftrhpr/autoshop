<?php
// Run this script once to create the 'technicians' and 'payroll_rules' tables.
require_once 'config.php';

try {
    $sql1 = "CREATE TABLE IF NOT EXISTS technicians (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL, -- optional link to users table
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(32) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql2 = "CREATE TABLE IF NOT EXISTS payroll_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        technician_id INT NOT NULL,
        rule_type ENUM('percentage','fixed_per_invoice') NOT NULL DEFAULT 'percentage',
        value DECIMAL(10,4) NOT NULL DEFAULT 0,
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql1);
    $pdo->exec($sql2);
    echo "technicians & payroll_rules tables created or already exist." . PHP_EOL;

    // Add technician columns to invoices table if missing
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = ?");
    $colCheck->execute(['technician_id']);
    if ($colCheck->fetchColumn() == 0){
        $pdo->exec("ALTER TABLE invoices ADD COLUMN technician_id INT NULL");
        echo "Added column technician_id to invoices." . PHP_EOL;
    }
    $colCheck->execute(['technician']);
    if ($colCheck->fetchColumn() == 0){
        $pdo->exec("ALTER TABLE invoices ADD COLUMN technician VARCHAR(255) NULL");
        echo "Added column technician to invoices." . PHP_EOL;
    }

} catch (PDOException $e) {
    echo "Error creating technicians tables: " . $e->getMessage() . PHP_EOL;
}
