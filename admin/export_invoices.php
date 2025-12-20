<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

// Optional: accept date range via GET
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$params = [];
$where = '';
if ($from) { $where .= ' AND created_at >= ?'; $params[] = $from; }
if ($to) { $where .= ' AND created_at <= ?'; $params[] = $to; }

$sql = "SELECT id, creation_date, customer_name, phone, car_mark, plate_number, mileage, parts_total, service_total, grand_total, created_at FROM invoices WHERE 1=1 " . $where . " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=invoices.csv');
$out = fopen('php://output', 'w');
// header
fputcsv($out, ['ID','Creation Date','Customer','Phone','Car','Plate','Mileage','Parts Total','Service Total','Grand Total','Saved At']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [$row['id'], $row['creation_date'], $row['customer_name'], $row['phone'], $row['car_mark'], $row['plate_number'], $row['mileage'], $row['parts_total'], $row['service_total'], $row['grand_total'], $row['created_at']]);
}

fclose($out);
exit;