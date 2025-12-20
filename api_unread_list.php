<?php
require 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit;
}
try{
    $stmt = $pdo->prepare('SELECT n.invoice_id, i.customer_name, i.plate_number, i.grand_total FROM invoice_notifications n JOIN invoices i ON i.id = n.invoice_id WHERE n.user_id = ? AND n.seen_at IS NULL ORDER BY n.created_at DESC LIMIT 50');
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true, 'unread' => count($rows), 'list' => $rows]);
}catch(Exception $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
