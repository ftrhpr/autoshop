<?php
require '../config.php';
header('Content-Type: application/json');

// Only admin users should be able to run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, customer_name, phone, car_mark, plate_number, items, parts_total, service_total, grand_total, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([date('Y-m-d'), 'Test Customer', '0000000000', 'TestCar', 'TEST-123', '[]', 0, 0, 0, $_SESSION['user_id'], $now]);
    $id = $pdo->lastInsertId();

    // Create notifications for admins/managers
    try {
        $usersStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','manager')");
        $userIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($userIds)){
            $ins = $pdo->prepare('INSERT INTO invoice_notifications (invoice_id, user_id) VALUES (?,?)');
            foreach ($userIds as $uid) { $ins->execute([$id, $uid]); }
        }
    } catch (Exception $e) { error_log('test_create_invoice notification error: '.$e->getMessage()); }

    echo json_encode(['success' => true, 'id' => (int)$id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
