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
        $stats = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];

        try {
            // Get stats based on user role
            if ($_SESSION['role'] === 'admin') {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count
                    FROM part_pricing_requests
                    GROUP BY status
                ");
                $stmt->execute();
            } elseif ($_SESSION['role'] === 'parts_collection_manager') {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count
                    FROM part_pricing_requests
                    WHERE assigned_to = ?
                    GROUP BY status
                ");
                $stmt->execute([$_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count
                    FROM part_pricing_requests
                    WHERE requested_by = ?
                    GROUP BY status
                ");
                $stmt->execute([$_SESSION['user_id']]);
            }

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                $stats[$row['status']] = (int)$row['count'];
            }
        } catch (Exception $e) {
            // If table doesn't exist, return empty stats
            $stats = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
        }

        echo json_encode(['success' => true, 'stats' => $stats]);
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