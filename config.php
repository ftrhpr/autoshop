<?php
// Database configuration
$host = 'localhost'; // Usually localhost for cPanel
$dbname = 'otoexpre_managers'; // Replace with your database name
$username = 'otoexpre_managers'; // Replace with your database username
$password = '5[VMnC@C8-!Stou6'; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session
session_start();

// Permission helpers
function roleHasPermission($pdo, $role, $permissionName) {
    // Backwards-compatible: no strict type hints or return types
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role = ? AND p.name = ?");
        $stmt->execute([$role, $permissionName]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        // If permissions table doesn't exist yet, treat as false (pre-seed state)
        error_log('Permission check failed: ' . $e->getMessage());
        return false;
    }
}

function currentUserCan($permissionName) {
    global $pdo;
    if (!isset($_SESSION['role'])) return false;
    $role = $_SESSION['role'];
    // admin shortcut
    if ($role === 'admin') return true;
    return roleHasPermission($pdo, $role, $permissionName);
}

?>