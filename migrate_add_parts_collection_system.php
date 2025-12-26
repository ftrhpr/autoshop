<?php
// Migration to add parts_collection_manager role and part_pricing_requests table
require_once 'config.php';

try {
    // Add new role to users table enum
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'parts_collection_manager', 'user') NOT NULL DEFAULT 'user'");

    // Create part_pricing_requests table
    $sql = "CREATE TABLE IF NOT EXISTS part_pricing_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        part_name VARCHAR(255) NOT NULL,
        part_description TEXT,
        requested_quantity DECIMAL(10,2) DEFAULT 1,
        vehicle_make VARCHAR(100),
        vehicle_model VARCHAR(100),
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        requested_price DECIMAL(10,2) NULL,
        final_price DECIMAL(10,2) NULL,
        notes TEXT,
        requested_by INT NOT NULL,
        assigned_to INT NULL,
        completed_by INT NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (status),
        INDEX (assigned_to),
        INDEX (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    echo "Part pricing requests system tables created successfully." . PHP_EOL;

    // Add permissions for parts collection manager
    $pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES
        ('manage_part_pricing', 'Manage part pricing requests'),
        ('view_part_pricing_requests', 'View part pricing requests')");

    // Assign permissions to parts collection manager role
    $pdo->exec("INSERT IGNORE INTO role_permissions (role, permission_id)
        SELECT 'parts_collection_manager', id FROM permissions WHERE name IN ('manage_part_pricing', 'view_part_pricing_requests')");

    echo "Permissions assigned to parts_collection_manager role." . PHP_EOL;

} catch (PDOException $e) {
    echo "Error setting up parts collection system: " . $e->getMessage() . PHP_EOL;
}