<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'parts_collection_manager', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $action = $_GET['action'] ?? 'test';

    if ($action === 'test') {
        echo json_encode(['success' => true, 'message' => 'API is working', 'user' => $_SESSION['user_id'] ?? 'none']);
    } elseif ($action === 'list') {
        echo json_encode(['success' => true, 'requests' => [], 'total' => 0]);
    } elseif ($action === 'stats') {
        echo json_encode(['success' => true, 'stats' => ['pending' => 0, 'in_progress' => 0, 'completed' => 0]]);
    } elseif ($action === 'activity') {
        echo json_encode(['success' => true, 'activities' => []]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>