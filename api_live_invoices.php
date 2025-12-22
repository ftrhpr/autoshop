<?php
require 'config.php';
header('Content-Type: application/json');

try {
    $lastTimestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

    // For initial timestamp request (no last_timestamp), allow without auth for debugging
    if ($lastTimestamp === null) {
        // Skip auth check for initial timestamp
        error_log("Live updates API: Initial timestamp request (no auth required)");
    } else {
        // Only managers and admins should get live invoice updates
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
            error_log("Live updates API: Unauthorized access attempt. User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Role: " . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized - please log in as manager or admin']);
            exit;
        }
        error_log("Live updates API: Authenticated request from user " . $_SESSION['user_id'] . " with last_timestamp: $lastTimestamp");
    }

    if ($lastTimestamp === null) {
        // Return the current latest timestamp
        $stmt = $pdo->query('SELECT MAX(updated_at) as max_timestamp FROM invoices');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max = $row['max_timestamp'] ?? null;
        error_log("Live updates API: Returning initial timestamp: $max");
        echo json_encode(['success' => true, 'latest_timestamp' => $max, 'new_count' => 0, 'invoices' => []]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT i.id, i.updated_at as created_at, i.customer_name, i.phone, i.car_mark, i.plate_number, i.vin, i.mileage, i.grand_total, i.is_new, i.opened_in_fina, u.username as sm_username FROM invoices i LEFT JOIN users u ON i.service_manager_id = u.id WHERE i.updated_at >= ? ORDER BY i.updated_at ASC');
    $stmt->execute([$lastTimestamp]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newCount = count($rows);
    $latestTimestamp = $lastTimestamp;
    if ($newCount > 0) {
        $latestTimestamp = end($rows)['created_at'];
    }

    error_log("Live updates API: Found $newCount updated invoices since $lastTimestamp, new latest: $latestTimestamp");
    echo json_encode(['success' => true, 'latest_timestamp' => $latestTimestamp, 'new_count' => $newCount, 'invoices' => $rows]);

} catch (Exception $e) {
    error_log("Live updates API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
