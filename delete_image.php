<?php
require 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['invoice_id']) || !isset($input['image_index'])) {
        throw new Exception('Invalid request data');
    }

    $invoiceId = (int)$input['invoice_id'];
    $imageIndex = (int)$input['image_index'];

    // Fetch current images
    $stmt = $pdo->prepare('SELECT images FROM invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception('Invoice not found');
    }

    $images = json_decode($row['images'], true) ?: [];

    if (!isset($images[$imageIndex])) {
        throw new Exception('Image not found');
    }

    // Get the image path to delete from filesystem
    $imagePath = $images[$imageIndex];

    // Remove from array
    array_splice($images, $imageIndex, 1);

    // Update database
    $stmt = $pdo->prepare('UPDATE invoices SET images = ? WHERE id = ?');
    $stmt->execute([json_encode($images, JSON_UNESCAPED_UNICODE), $invoiceId]);

    // Try to delete file from filesystem
    $fullPath = __DIR__ . '/' . $imagePath;
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>