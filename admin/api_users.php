<?php
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode([]);
    exit;
}

$q = $_GET['q'] ?? '';
$q = trim($q);

if ($q === '') {
    // return small list of users
    $stmt = $pdo->query('SELECT id, username FROM users ORDER BY username LIMIT 20');
    echo json_encode($stmt->fetchAll());
    exit;
}

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE username LIKE ? ORDER BY username LIMIT 20');
$stmt->execute(["%$q%"]);
$rows = $stmt->fetchAll();

echo json_encode($rows);
exit;