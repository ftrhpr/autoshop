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

    // Include unread flag if table exists, otherwise simple select
    $hasNotifications = (bool)$pdo->query("SHOW TABLES LIKE 'invoice_notifications'")->fetch();
    if ($hasNotifications){
        $stmt = $pdo->prepare('SELECT i.id, i.created_at, i.customer_name, i.plate_number, i.grand_total, (CASE WHEN n.seen_at IS NULL THEN 1 ELSE 0 END) AS unread FROM invoices i LEFT JOIN invoice_notifications n ON (n.invoice_id = i.id AND n.user_id = ?) WHERE i.id > ? ORDER BY i.id ASC');
        $stmt->execute([$_SESSION['user_id'], $lastId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, created_at, customer_name, plate_number, grand_total FROM invoices WHERE id > ? ORDER BY id ASC');
        $stmt->execute([$lastId]);
    }
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
