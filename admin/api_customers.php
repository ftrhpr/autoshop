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
    $stmt = $pdo->prepare('SELECT id, full_name, plate_number FROM customers WHERE plate_number LIKE ? OR full_name LIKE ? LIMIT 20');
    $stmt->execute(["%$q%","%$q%"]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

echo json_encode([]);
exit;