<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])){ echo json_encode(['success'=>false,'error'=>'access_denied']); exit; }
try{
    $stmt = $pdo->query('SELECT id, name FROM technicians ORDER BY name');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'technicians'=>$rows]);
} catch (Exception $e){ echo json_encode(['success'=>false,'error'=>'exception','message'=>$e->getMessage()]); }
