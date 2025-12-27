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
        try {
            // List part pricing requests based on user role
            $status = $_GET['status'] ?? 'all';
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $whereConditions = [];
            $params = [];

            if ($_SESSION['role'] === 'parts_collection_manager') {
                $whereConditions[] = "assigned_to = ?";
                $params[] = $_SESSION['user_id'];
            } elseif ($_SESSION['role'] === 'admin') {
                // Admins see all requests
            } elseif ($_SESSION['role'] === 'manager') {
                // Regular managers see requests they created
                $whereConditions[] = "requested_by = ?";
                $params[] = $_SESSION['user_id'];
            }

            if ($status !== 'all') {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "
                SELECT ppr.*,
                       i.customer_name, i.plate_number, i.car_mark,
                       rb.username as requested_by_name,
                       ab.username as assigned_to_name,
                       cb.username as completed_by_name
                FROM part_pricing_requests ppr
                LEFT JOIN invoices i ON ppr.invoice_id = i.id
                LEFT JOIN users rb ON ppr.requested_by = rb.id
                LEFT JOIN users ab ON ppr.assigned_to = ab.id
                LEFT JOIN users cb ON ppr.completed_by = cb.id
            " . $whereClause . "
                ORDER BY ppr.created_at DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM part_pricing_requests ppr {$whereClause}");
            array_pop($params); // Remove limit
            array_pop($params); // Remove offset
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'requests' => $requests,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            // If table doesn't exist or other error, return empty results
            echo json_encode([
                'success' => true,
                'requests' => [],
                'total' => 0,
                'limit' => (int)($_GET['limit'] ?? 50),
                'offset' => (int)($_GET['offset'] ?? 0)
            ]);
        }
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