<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

echo json_encode(['success' => true, 'user_id' => $_SESSION['user_id'], 'role' => $_SESSION['role'] ?? null]);
