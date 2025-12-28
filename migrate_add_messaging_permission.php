<?php
// Migration: Add messaging permission for inbox functionality
// Run this script to add messaging permission to existing databases
require_once 'config.php';

try {
    echo "Starting messaging permission migration...\n";

    // Insert messaging permission if it doesn't exist
    $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)");
    $stmt->execute(['send_messages', 'Send and receive messages']);

    echo "✓ Added messaging permission\n";

    // Get the permission ID
    $perm_id = $pdo->lastInsertId();
    if (!$perm_id) {
        // If INSERT IGNORE didn't insert, get existing ID
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute(['send_messages']);
        $perm_id = $stmt->fetchColumn();
    }

    if ($perm_id) {
        // Assign to manager and user roles
        $roles = ['manager', 'user', 'parts_collection_manager'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, permission_id) VALUES (?, ?)");
        foreach ($roles as $role) {
            $stmt->execute([$role, $perm_id]);
        }
        echo "✓ Assigned messaging permission to manager, user, and parts_collection_manager roles\n";
    }

    echo "Messaging permission migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Error during messaging permission migration: " . $e->getMessage() . "\n";
}