<?php
// Database configuration
// Check if we're on live server or localhost
if ($_SERVER['HTTP_HOST'] === 'new.otoexpress.ge' || strpos($_SERVER['HTTP_HOST'], 'otoexpress.ge') !== false) {
    // Live server configuration
    $host = 'localhost'; // Usually localhost even on live servers
    $dbname = 'otoexpre_managers'; // Update this for live database
    $username = 'otoexpre_managers'; // Update this for live database
    $password = '5[VMnC@C8-!Stou6'; // Update this for live database
} else {
    // Local development configuration
    $host = 'localhost';
    $dbname = 'otoexpre_managers';
    $username = 'otoexpre_managers';
    $password = '5[VMnC@C8-!Stou6';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Use native prepared statements so numeric LIMIT/OFFSET params are not quoted
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // For API endpoints, don't die - let them handle the error gracefully
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || strpos($_SERVER['REQUEST_URI'], 'api_') !== false) {
        $pdo = null; // Set to null so API can check
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Feature flags
// Set to true to enable Server-Sent Events (SSE) for live notifications. Disabled by default to avoid long-lived PHP processes on single-threaded servers.
$enable_sse = false;

// Start session
session_start();

// Permission helpers
function roleHasPermission(PDO $pdo, string $role, string $permissionName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role = ? AND p.name = ?");
    $stmt->execute([$role, $permissionName]);
    return (int)$stmt->fetchColumn() > 0;
}

function currentUserCan(string $permissionName): bool {
    global $pdo;
    if (!isset($_SESSION['role'])) return false;
    $role = $_SESSION['role'];
    // admin shortcut
    if ($role === 'admin') return true;
    return roleHasPermission($pdo, $role, $permissionName);
}

?>