<?php
// Simple test - remove all dependencies to isolate the issue
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'Basic API test working',
    'timestamp' => time(),
    'request' => $_GET
]);
exit;
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
        $activities = [];

        try {
            $limit = (int)($_GET['limit'] ?? 10);

            // Get recent activities based on user role
            $whereClause = '';
            $params = [];

            if ($_SESSION['role'] === 'parts_collection_manager') {
                $whereClause = "WHERE ppr.assigned_to = ?";
                $params[] = $_SESSION['user_id'];
            } elseif ($_SESSION['role'] === 'manager') {
                $whereClause = "WHERE ppr.requested_by = ?";
                $params[] = $_SESSION['user_id'];
            }
            // Admin sees all activities

            $sql = "
                SELECT
                    CONCAT('request_', ppr.id, '_', ppr.status) as id,
                    CASE
                        WHEN ppr.status = 'pending' THEN 'created'
                        WHEN ppr.status = 'in_progress' THEN 'assigned'
                        WHEN ppr.status = 'completed' THEN 'completed'
                        ELSE 'created'
                    END as type,
                    CASE
                        WHEN ppr.status = 'pending' THEN CONCAT('New request created: ', ppr.part_name)
                        WHEN ppr.status = 'in_progress' THEN CONCAT('Request assigned: ', ppr.part_name)
                        WHEN ppr.status = 'completed' THEN CONCAT('Request completed: ', ppr.part_name)
                        ELSE CONCAT('Request updated: ', ppr.part_name)
                    END as message,
                    ppr.updated_at as created_at
                FROM part_pricing_requests ppr
                {$whereClause}
                ORDER BY ppr.updated_at DESC
                LIMIT ?
            ";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            // If table doesn't exist or error, return empty activities
            $activities = [];
        }

        echo json_encode(['success' => true, 'activities' => $activities]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if ($action === 'assign') {
            // Assign request to current user
            $requestId = (int)($_POST['request_id'] ?? $data['request_id'] ?? 0);

            error_log("Assign request: ID=$requestId, User=" . $_SESSION['user_id']);
            error_log("POST data: " . json_encode($_POST));
            error_log("JSON data: " . json_encode($data));

            try {
                $pdo->beginTransaction();

                // Check current assignment
                $checkStmt = $pdo->prepare("SELECT assigned_to, status FROM part_pricing_requests WHERE id = ?");
                $checkStmt->execute([$requestId]);
                $current = $checkStmt->fetch();

                error_log("Current request: " . json_encode($current));

                if (!$current) {
                    throw new Exception('Request not found');
                }

                if ($current['assigned_to'] && $current['assigned_to'] != $_SESSION['user_id']) {
                    throw new Exception('Request already assigned to another user');
                }

                // Assign the request
                $stmt = $pdo->prepare("
                    UPDATE part_pricing_requests
                    SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                    WHERE id = ? AND (assigned_to IS NULL OR assigned_to = ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $requestId, $_SESSION['user_id']]);

                $affected = $stmt->rowCount();
                error_log("Assign update affected: $affected rows");

                if ($affected === 0) {
                    throw new Exception('Failed to assign request');
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Request assigned successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Assign error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }

        } elseif ($action === 'update_price') {
            // Update price for a request
            $requestId = (int)($data['id'] ?? 0);
            $price = (float)($data['final_price'] ?? 0);
            $notes = trim($data['notes'] ?? '');

            error_log("Update price request: ID=$requestId, Price=$price, Notes=$notes, User=" . $_SESSION['user_id']);
            error_log("POST data: " . json_encode($_POST));
            error_log("JSON data: " . json_encode($data));

            try {
                $pdo->beginTransaction();

                // Check current request status
                $checkStmt = $pdo->prepare("SELECT status, assigned_to, part_name FROM part_pricing_requests WHERE id = ?");
                $checkStmt->execute([$requestId]);
                $currentRequest = $checkStmt->fetch();

                error_log("Current request: " . json_encode($currentRequest));

                if (!$currentRequest) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Request not found']);
                    exit;
                }

                // If request is pending, assign it to current user first
                if ($currentRequest['status'] === 'pending') {
                    error_log("Assigning pending request");
                    $assignStmt = $pdo->prepare("
                        UPDATE part_pricing_requests
                        SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                        WHERE id = ? AND status = 'pending'
                    ");
                    $assignStmt->execute([$_SESSION['user_id'], $requestId]);
                    error_log("Assign result: " . $assignStmt->rowCount() . " rows");
                }
                // If request is in_progress but not assigned, assign it
                elseif ($currentRequest['status'] === 'in_progress' && $currentRequest['assigned_to'] == null) {
                    error_log("Assigning unassigned in_progress request");
                    $assignStmt = $pdo->prepare("
                        UPDATE part_pricing_requests
                        SET assigned_to = ?, updated_at = NOW()
                        WHERE id = ? AND status = 'in_progress' AND assigned_to IS NULL
                    ");
                    $assignStmt->execute([$_SESSION['user_id'], $requestId]);
                    error_log("Assign result: " . $assignStmt->rowCount() . " rows");
                }
                // If request is in_progress and assigned to someone else, check if it's assigned to current user
                elseif ($currentRequest['status'] === 'in_progress' && $currentRequest['assigned_to'] != $_SESSION['user_id']) {
                    error_log("Request assigned to different user");
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Request is already assigned to another user']);
                    exit;
                }

                // Now update the price
                error_log("Updating price");
                $stmt = $pdo->prepare("
                    UPDATE part_pricing_requests
                    SET final_price = ?, notes = ?, updated_at = NOW()
                    WHERE id = ? AND assigned_to = ? AND status = 'in_progress'
                ");
                $stmt->execute([$price, $notes, $requestId, $_SESSION['user_id']]);

                $affectedRows = $stmt->rowCount();
                error_log("Price update result: $affectedRows rows affected");

                if ($affectedRows > 0) {
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
                } else {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to update price - request may not be in correct state']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Update price error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }

        } elseif ($action === 'complete') {
            // Mark request as completed
            $requestId = (int)($data['request_id'] ?? 0);

            try {
                $pdo->beginTransaction();

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

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Request completed successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
?>