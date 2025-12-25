<?php
// API to get vehicle makes for suggestions
require 'config.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM vehicle_makes WHERE name LIKE ? ORDER BY name LIMIT 10");
    $stmt->execute(['%' . $query . '%']);
    $makes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($makes);
} catch (Exception $e) {
    echo json_encode([]);
}
?>