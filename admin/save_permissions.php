<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$roles = ['admin','manager','user'];

// Clear and save
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM role_permissions');
    $insert = $pdo->prepare('INSERT INTO role_permissions (role, permission_id) VALUES (?, ?)');
    foreach ($roles as $r) {
        $keys = $_POST["perm_$r"] ?? [];
        foreach ($keys as $pid) {
            $insert->execute([$r, (int)$pid]);
        }
    }
    // Log action
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, 'update_permissions', ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'updated role permissions', $_SERVER['REMOTE_ADDR'] ?? '']);

    $pdo->commit();
    header('Location: permissions.php?success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error saving permissions: ' . $e->getMessage());
}
?>