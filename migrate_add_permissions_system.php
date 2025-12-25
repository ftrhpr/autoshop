<?php
// Migration: Add permissions system for role-based access control
// Run this script to add the permissions and role_permissions tables to existing databases
require_once 'config.php';

try {
    echo "Starting permissions system migration...\n";

    // Create permissions table
    $sql = "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    echo "✓ Created permissions table\n";

    // Create role_permissions table
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('admin', 'manager', 'user') NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_role_permission (role, permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    echo "✓ Created role_permissions table\n";

    // Insert default permissions (only if they don't already exist)
    $permissions = [
        ['view_analytics', 'View analytics and reports'],
        ['create_invoices', 'Create new invoices'],
        ['view_invoices', 'View existing invoices'],
        ['export_invoices', 'Export invoices to file'],
        ['manage_customers', 'Manage customer database'],
        ['manage_vehicles', 'Manage vehicle database'],
        ['manage_prices', 'Manage parts, labor, and oil prices'],
        ['manage_users', 'Manage user accounts'],
        ['manage_permissions', 'Manage roles and permissions'],
        ['view_reports', 'View usage reports'],
        ['view_logs', 'View audit logs']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)");
    foreach ($permissions as $perm) {
        $stmt->execute($perm);
    }
    echo "✓ Inserted default permissions\n";

    // Get permission IDs for role assignments
    $stmt = $pdo->query("SELECT id, name FROM permissions");
    $permIds = [];
    while ($row = $stmt->fetch()) {
        $permIds[$row['name']] = $row['id'];
    }

    // Assign permissions to roles (only if not already assigned)
    $roleAssignments = [
        'admin' => ['view_analytics', 'create_invoices', 'view_invoices', 'export_invoices',
                   'manage_customers', 'manage_vehicles', 'manage_prices', 'manage_users',
                   'manage_permissions', 'view_reports', 'view_logs'],
        'manager' => ['view_analytics', 'create_invoices', 'view_invoices', 'manage_customers', 'manage_vehicles'],
        'user' => ['create_invoices', 'view_invoices']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, permission_id) VALUES (?, ?)");
    foreach ($roleAssignments as $role => $perms) {
        foreach ($perms as $permName) {
            if (isset($permIds[$permName])) {
                $stmt->execute([$role, $permIds[$permName]]);
            }
        }
    }
    echo "✓ Assigned permissions to roles\n";

    echo "Migration completed successfully!\n";
    echo "\nPermission system is now active. Menu items will be filtered based on user roles.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>