<?php
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid invoice ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE invoices SET is_new = 0 WHERE id = ?');
    $stmt->execute([$invoice_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>