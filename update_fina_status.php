<?php
require 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is manager/admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions. Role: ' . ($_SESSION['role'] ?? 'none')]);
    exit;
}

try {
    // Debug: Log the raw input
    $rawInput = file_get_contents('php://input');
    error_log('Raw input: ' . $rawInput);

    $input = json_decode($rawInput, true);

    // Debug: Check if JSON decoding worked
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!$input || !isset($input['invoice_id']) || !isset($input['opened_in_fina'])) {
        throw new Exception('Invalid request data - missing invoice_id or opened_in_fina');
    }

    $invoiceId = (int)$input['invoice_id'];
    $openedInFina = (int)$input['opened_in_fina'];

    // Debug logging
    error_log("FINA status update request: invoice_id=$invoiceId, opened_in_fina=$openedInFina, user_id=" . ($_SESSION['user_id'] ?? 'none'));

    // Validate opened_in_fina is 0 or 1
    if ($openedInFina !== 0 && $openedInFina !== 1) {
        throw new Exception('Invalid FINA status value - must be 0 or 1');
    }

    // Check if opened_in_fina column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'opened_in_fina'");
    $stmt->execute();
    $columnExists = (int)$stmt->fetchColumn();

    if (!$columnExists) {
        throw new Exception('FINA status column does not exist. Please run the migration at: https://new.otoexpress.ge/migration_runner.html');
    }

    // First check if the invoice exists and get its current status
    $stmt = $pdo->prepare('SELECT id, opened_in_fina FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        error_log("Invoice not found in database: $invoiceId");
        throw new Exception("Invoice with ID $invoiceId not found in database");
    }

    error_log("Found invoice $invoiceId, current FINA status: " . $invoice['opened_in_fina']);

    // If the status is already what we want, consider it successful
    if ((int)$invoice['opened_in_fina'] === $openedInFina) {
        error_log("FINA status already correct for invoice $invoiceId");
        echo json_encode(['success' => true, 'message' => 'FINA status is already set to the desired value']);
        exit;
    }

    // Update the invoice
    $stmt = $pdo->prepare('UPDATE invoices SET opened_in_fina = ? WHERE id = ?');
    $result = $stmt->execute([$openedInFina, $invoiceId]);

    if (!$result) {
        error_log("Database update failed for invoice $invoiceId");
        throw new Exception('Database update failed');
    }

    $affectedRows = $stmt->rowCount();
    error_log("Update affected $affectedRows rows for invoice $invoiceId");

    // Check if any rows were affected (should be 1 for a successful update)
    if ($affectedRows === 0) {
        throw new Exception('Invoice not found or update failed');
    }

    // Log the action (only if audit_logs table exists)
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user_id'],
            'update_fina_status',
            "invoice_id={$invoiceId}, opened_in_fina={$openedInFina}",
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        // Ignore audit log errors - not critical
        error_log('Failed to log FINA status update: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'FINA status updated successfully']);

} catch (Exception $e) {
    error_log('FINA status update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>