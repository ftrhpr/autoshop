<?php
require 'config.php';
header('Content-Type: application/json');

// Only managers and admins should get new invoice notifications
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : null;

    if ($lastId === null) {
        // Return the current latest id
        $stmt = $pdo->query('SELECT MAX(id) as max_id FROM invoices');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max = (int)($row['max_id'] ?? 0);
        echo json_encode(['success' => true, 'latest_id' => $max, 'new_count' => 0, 'invoices' => []]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, created_at, customer_name, plate_number, grand_total FROM invoices WHERE id > ? ORDER BY id ASC');
    $stmt->execute([$lastId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newCount = count($rows);
    $latestId = $lastId;
    if ($newCount > 0) {
        $latestId = (int)end($rows)['id'];
    }

    echo json_encode(['success' => true, 'latest_id' => $latestId, 'new_count' => $newCount, 'invoices' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
