<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');

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
?>