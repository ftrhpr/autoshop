<?php
require 'config.php';
header('Content-Type: application/json');

// Only managers and admins should get live invoice updates
// Temporarily disabled for testing
/*
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
*/

try {
    $lastTimestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

    if ($lastTimestamp === null) {
        // Return the current latest timestamp
        $stmt = $pdo->query('SELECT MAX(created_at) as max_timestamp FROM invoices');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max = $row['max_timestamp'] ?? null;
        echo json_encode(['success' => true, 'latest_timestamp' => $max, 'new_count' => 0, 'invoices' => []]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, created_at, customer_name, plate_number, grand_total FROM invoices WHERE created_at > ? ORDER BY created_at ASC');
    $stmt->execute([$lastTimestamp]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newCount = count($rows);
    $latestTimestamp = $lastTimestamp;
    if ($newCount > 0) {
        $latestTimestamp = end($rows)['created_at'];
    }

    echo json_encode(['success' => true, 'latest_timestamp' => $latestTimestamp, 'new_count' => $newCount, 'invoices' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
