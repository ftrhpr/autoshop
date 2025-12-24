<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])){ echo json_encode([]); exit; }
$q = trim($_GET['q'] ?? '');
try{
    if ($q === ''){
        $stmt = $pdo->prepare('SELECT id, name FROM technicians ORDER BY name LIMIT 50');
        $stmt->execute();
    } else {
        $like = "%" . $q . "%";
        $stmt = $pdo->prepare('SELECT id, name FROM technicians WHERE name LIKE ? ORDER BY name LIMIT 50');
        $stmt->execute([$like]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'technicians' => $rows]);
} catch (Exception $e){ echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
