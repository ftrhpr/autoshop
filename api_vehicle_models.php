<?php
// API to get vehicle models for suggestions
require 'config.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$make_id = $_GET['make_id'] ?? null;

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT id, name FROM vehicle_models WHERE name LIKE ?";
    $params = ['%' . $query . '%'];

    if ($make_id) {
        $sql .= " AND make_id = ?";
        $params[] = $make_id;
    }

    $sql .= " ORDER BY name LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($models);
} catch (Exception $e) {
    echo json_encode([]);
}
?>