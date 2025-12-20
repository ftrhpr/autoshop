<?php
require 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'error'=>'Unauthorized']);
    exit;
}
try{
    $stmt = $pdo->prepare('UPDATE invoice_notifications SET seen_at = NOW() WHERE user_id = ? AND seen_at IS NULL');
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success'=>true, 'rows' => $stmt->rowCount()]);
}catch(Exception $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
