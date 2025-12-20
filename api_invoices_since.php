<?php
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    // Build filters from query params (same as manager.php)
    $filters = ['i.id > ?'];
    $params = [$lastId];

    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $filters[] = '(i.customer_name LIKE ? OR i.plate_number LIKE ? OR i.vin LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $plate = trim($_GET['plate'] ?? '');
    if ($plate !== '') { $filters[] = 'i.plate_number LIKE ?'; $params[] = "%$plate%"; }
    $vin = trim($_GET['vin'] ?? '');
    if ($vin !== '') { $filters[] = 'i.vin LIKE ?'; $params[] = "%$vin%"; }
    $sm = trim($_GET['sm'] ?? '');
    if ($sm !== '') { $filters[] = 'u.username LIKE ?'; $params[] = "%$sm%"; }
    $dateFrom = trim($_GET['date_from'] ?? '');
    if ($dateFrom !== '') { $filters[] = 'i.creation_date >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    $dateTo = trim($_GET['date_to'] ?? '');
    if ($dateTo !== '') { $filters[] = 'i.creation_date <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $minTotal = trim($_GET['min_total'] ?? '');
    if ($minTotal !== '' && is_numeric($minTotal)) { $filters[] = 'i.grand_total >= ?'; $params[] = (float)$minTotal; }
    $maxTotal = trim($_GET['max_total'] ?? '');
    if ($maxTotal !== '' && is_numeric($maxTotal)) { $filters[] = 'i.grand_total <= ?'; $params[] = (float)$maxTotal; }
    $finaStatus = trim($_GET['fina_status'] ?? '');
    if ($finaStatus !== '') {
        if ($finaStatus === 'opened') { $filters[] = 'i.opened_in_fina = 1'; }
        elseif ($finaStatus === 'not_opened') { $filters[] = 'i.opened_in_fina = 0'; }
    }

    // Include unread flag for the current user
    $sql = 'SELECT i.*, u.username AS sm_username, (CASE WHEN n.seen_at IS NULL THEN 1 ELSE 0 END) AS unread FROM invoices i LEFT JOIN users u ON i.service_manager_id = u.id LEFT JOIN invoice_notifications n ON (n.invoice_id = i.id AND n.user_id = ?)';
    // current user id must be the first param for the notification join
    array_unshift($params, $_SESSION['user_id']);
    if (!empty($filters)) { $sql .= ' WHERE ' . implode(' AND ', $filters); }
    $sql .= ' ORDER BY i.id ASC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // compute latest id from rows if present
    $latest = $lastId;
    foreach ($rows as $r){ if (!empty($r['id']) && $r['id'] > $latest) $latest = (int)$r['id']; }

    echo json_encode(['success' => true, 'latest_id' => $latest, 'count' => count($rows), 'invoices' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
