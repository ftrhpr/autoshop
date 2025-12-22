<?php
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(null);
    exit;
}

$id = $_GET['id'] ?? null;
$plate = $_GET['plate'] ?? null;
$q = $_GET['q'] ?? null;
$phone = $_GET['phone'] ?? null;

header('Content-Type: application/json; charset=utf-8');

if ($id) {
    $stmt = $pdo->prepare('SELECT v.*, c.full_name, c.phone, c.email, c.notes FROM vehicles v JOIN customers c ON v.customer_id = c.id WHERE v.id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

if ($plate) {
    $stmt = $pdo->prepare('SELECT v.id, c.full_name, c.phone, c.email, c.notes, v.plate_number, v.car_mark, v.vin, v.mileage FROM customers c JOIN vehicles v ON c.id = v.customer_id WHERE v.plate_number = ? LIMIT 1');
    $stmt->execute([strtoupper($plate)]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

if ($q) {
    $stmt = $pdo->prepare('SELECT v.id, c.full_name, c.phone, v.plate_number, v.car_mark FROM customers c JOIN vehicles v ON c.id = v.customer_id WHERE v.plate_number LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? LIMIT 20');
    $stmt->execute(["%$q%","%$q%","%$q%"]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

$customer_q = $_GET['customer_q'] ?? null;

if ($customer_q) {
    $stmt = $pdo->prepare('SELECT id, full_name, phone FROM customers WHERE full_name LIKE ? OR phone LIKE ? LIMIT 20');
    $stmt->execute(["%$customer_q%","%$customer_q%"]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($phone) {
    $stmt = $pdo->prepare('SELECT v.id, c.full_name, c.phone, c.email, c.notes, v.plate_number, v.car_mark, v.vin, v.mileage FROM customers c JOIN vehicles v ON c.id = v.customer_id WHERE c.phone = ? LIMIT 1');
    $stmt->execute([trim($phone)]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

echo json_encode([]);
exit;