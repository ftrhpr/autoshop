<?php
require 'config.php';
try {
    // Table for oil brands
    $pdo->exec("
        CREATE TABLE oil_brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Table for oil viscosities
    $pdo->exec("
        CREATE TABLE oil_viscosities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            viscosity VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Table for oil packages/prices
    $pdo->exec("
        CREATE TABLE oil_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            viscosity_id INT NOT NULL,
            package_type ENUM('5L', '4L', '1L', 'canned') NOT NULL,
            amount DECIMAL(10,2) NULL, -- NULL for standard packages, custom amount for canned
            price DECIMAL(10,2) NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES oil_brands(id) ON DELETE CASCADE,
            FOREIGN KEY (viscosity_id) REFERENCES oil_viscosities(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_package (brand_id, viscosity_id, package_type, amount)
        )
    ");

    // Insert some default viscosities
    $pdo->exec("
        INSERT INTO oil_viscosities (viscosity, description) VALUES
        ('0W-20', 'Fully synthetic, excellent cold start protection'),
        ('0W-30', 'Fully synthetic, balanced performance'),
        ('5W-30', 'Synthetic blend, good all-round performance'),
        ('5W-40', 'Synthetic, high performance'),
        ('10W-40', 'Mineral/synthetic blend, versatile'),
        ('15W-40', 'Heavy duty, diesel engines')
    ");

    echo "Oil system tables created successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}