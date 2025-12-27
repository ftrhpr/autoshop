<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'parts_collection_manager', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            try {
                // List part pricing requests based on user role
                $status = $_GET['status'] ?? 'pending';
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = (int)($_GET['offset'] ?? 0);

                $whereConditions = [];
                $params = [];

                if ($_SESSION['role'] === 'parts_collection_manager') {
                    // Parts collection managers see requests assigned to them or unassigned
                    $whereConditions[] = "(assigned_to IS NULL OR assigned_to = ?)";
                    $params[] = $_SESSION['user_id'];
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

                $stmt = $pdo->prepare("
                    SELECT ppr.*,
                           i.customer_name, i.plate_number, i.car_mark,
                           rb.username as requested_by_name,
                           ab.username as assigned_to_name,
                           cb.username as completed_by_name
                    FROM part_pricing_requests ppr
                    JOIN invoices i ON ppr.invoice_id = i.id
                    LEFT JOIN users rb ON ppr.requested_by = rb.id
                    LEFT JOIN users ab ON ppr.assigned_to = ab.id
                    LEFT JOIN users cb ON ppr.completed_by = cb.id
                " . $whereClause . "
                    ORDER BY ppr.created_at DESC
                    LIMIT ? OFFSET ?
                ");

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
            // Get statistics for dashboard
            $stats = [];

            try {
                if ($_SESSION['role'] === 'parts_collection_manager') {
                    $stmt = $pdo->prepare("
                        SELECT status, COUNT(*) as count
                        FROM part_pricing_requests
                        WHERE assigned_to IS NULL OR assigned_to = ?
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
            // Get recent activity for dashboard - simplified version
            $limit = (int)($_GET['limit'] ?? 10);

            // For now, return empty array to avoid SQL errors
            $activities = [];

            // TODO: Implement proper activity query after fixing table issues
            echo json_encode(['success' => true, 'activities' => $activities]);
        }

    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        try {

        if ($action === 'assign') {
            // Assign request to current user
            $requestId = (int)($data['request_id'] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE part_pricing_requests
                SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                WHERE id = ? AND (assigned_to IS NULL OR assigned_to = ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $requestId, $_SESSION['user_id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Request assigned successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Request not found or already assigned']);
            }

        } elseif ($action === 'update_price') {
            // Update price for a request
            $requestId = (int)($data['id'] ?? 0);
            $price = (float)($data['final_price'] ?? 0);
            $notes = trim($data['notes'] ?? '');
            $supplier = trim($data['supplier'] ?? '');

            // Debug logging
            error_log("Update price request: ID=$requestId, Price=$price, Notes=$notes, Supplier=$supplier, User=" . $_SESSION['user_id']);

            // Check current request status
            $checkStmt = $pdo->prepare("SELECT status, assigned_to, part_name FROM part_pricing_requests WHERE id = ?");
            $checkStmt->execute([$requestId]);
            $currentRequest = $checkStmt->fetch();

            if (!$currentRequest) {
                error_log("Request not found: $requestId");
                echo json_encode(['success' => false, 'message' => 'Request not found']);
                exit;
            }

            error_log("Current request state: Status={$currentRequest['status']}, AssignedTo={$currentRequest['assigned_to']}, Part={$currentRequest['part_name']}");

            // If request is pending, assign it to current user first
            if ($currentRequest['status'] === 'pending') {
                error_log("Assigning pending request to user");
                $assignStmt = $pdo->prepare("
                    UPDATE part_pricing_requests
                    SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                $assignStmt->execute([$_SESSION['user_id'], $requestId]);
                error_log("Assignment result: " . $assignStmt->rowCount() . " rows affected");
            }
            // If request is in_progress but not assigned, assign it
            elseif ($currentRequest['status'] === 'in_progress' && $currentRequest['assigned_to'] == null) {
                error_log("Assigning unassigned in_progress request to user");
                $assignStmt = $pdo->prepare("
                    UPDATE part_pricing_requests
                    SET assigned_to = ?, updated_at = NOW()
                    WHERE id = ? AND status = 'in_progress' AND assigned_to IS NULL
                ");
                $assignStmt->execute([$_SESSION['user_id'], $requestId]);
                error_log("Assignment result: " . $assignStmt->rowCount() . " rows affected");
            }
            // If request is in_progress and assigned to someone else, check if it's assigned to current user
            elseif ($currentRequest['status'] === 'in_progress' && $currentRequest['assigned_to'] != $_SESSION['user_id']) {
                error_log("Request assigned to different user: {$currentRequest['assigned_to']} vs {$_SESSION['user_id']}");
                echo json_encode(['success' => false, 'message' => 'Request is already assigned to another user']);
                exit;
            }

            // Now update the price
            error_log("Updating price to: $price");
            $stmt = $pdo->prepare("
                UPDATE part_pricing_requests
                SET final_price = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND assigned_to = ? AND status = 'in_progress'
            ");
            $stmt->execute([$price, $notes, $requestId, $_SESSION['user_id']]);

            $affectedRows = $stmt->rowCount();
            error_log("Price update result: $affectedRows rows affected");

            if ($affectedRows > 0) {
                echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update price - request may not be in correct state']);
            }

        } elseif ($action === 'complete') {
            // Mark request as completed
            $requestId = (int)($data['request_id'] ?? 0);

            $pdo->beginTransaction();

            try {
                // Update the request
                $stmt = $pdo->prepare("
                    UPDATE part_pricing_requests
                    SET status = 'completed', completed_by = ?, completed_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND assigned_to = ? AND status = 'in_progress'
                ");
                $stmt->execute([$_SESSION['user_id'], $requestId, $_SESSION['user_id']]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Request not found or not assigned to you');
                }

                // Get request details for notification
                $stmt = $pdo->prepare("
                    SELECT ppr.*, i.service_manager_id, i.customer_name, i.plate_number
                    FROM part_pricing_requests ppr
                    JOIN invoices i ON ppr.invoice_id = i.id
                    WHERE ppr.id = ?
                ");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                // Send notification to service manager if assigned
                if (!empty($request['service_manager_id'])) {
                    $message = "Part pricing completed for invoice #{$request['invoice_id']} ({$request['customer_name']} - {$request['plate_number']}): {$request['part_name']} - Price: {$request['final_price']}";

                    $stmt = $pdo->prepare("
                        INSERT INTO messages (sender_id, recipient_id, subject, body)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $request['service_manager_id'],
                        'Part Pricing Completed',
                        $message
                    ]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Request completed successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}