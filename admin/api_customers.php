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
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

if ($plate) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE plate_number = ? LIMIT 1');
    $stmt->execute([strtoupper($plate)]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

if ($q) {
    $stmt = $pdo->prepare('SELECT id, full_name, plate_number, phone FROM customers WHERE plate_number LIKE ? OR full_name LIKE ? OR phone LIKE ? LIMIT 20');
    $stmt->execute(["%$q%","%$q%","%$q%"]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($phone) {
    $stmt = $pdo->prepare('SELECT id, full_name, plate_number, phone FROM customers WHERE phone = ? LIMIT 1');
    $stmt->execute([trim($phone)]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

echo json_encode([]);
exit;