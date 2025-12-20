<?php
require 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is manager/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['invoice_id']) || !isset($input['opened_in_fina'])) {
        throw new Exception('Invalid request data');
    }

    $invoiceId = (int)$input['invoice_id'];
    $openedInFina = (int)$input['opened_in_fina'];

    // Validate opened_in_fina is 0 or 1
    if ($openedInFina !== 0 && $openedInFina !== 1) {
        throw new Exception('Invalid FINA status value');
    }

    // Update the invoice
    $stmt = $pdo->prepare('UPDATE invoices SET opened_in_fina = ? WHERE id = ?');
    $stmt->execute([$openedInFina, $invoiceId]);

    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invoice not found or no changes made');
    }

    // Log the action
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'],
        'update_fina_status',
        "invoice_id={$invoiceId}, opened_in_fina={$openedInFina}",
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>