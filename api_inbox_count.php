<?php
require_once 'config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0');
    $stmt->execute([ (int)$_SESSION['user_id'] ]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'unread_count' => $count]);
} catch (PDOException $e) {
    // If table doesn't exist or other error, return zero and an error field
    echo json_encode(['success' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}
