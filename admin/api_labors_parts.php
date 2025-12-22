<?php
require '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode([]);
    exit;
}

// Search both labors and parts
$results = [];

$stmt = $pdo->prepare("SELECT name, default_price, 'labor' as type FROM labors WHERE name LIKE ? ORDER BY name LIMIT 5");
$stmt->execute(["%$query%"]);
$labors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT name, default_price, 'part' as type FROM parts WHERE name LIKE ? ORDER BY name LIMIT 5");
$stmt->execute(["%$query%"]);
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = array_merge($labors, $parts);

echo json_encode($results);
?>