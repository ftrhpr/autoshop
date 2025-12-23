<?php
require '../config.php';

// Full JSON API for Labors and Parts with CRUD & CSV export
header('Content-Type: application/json; charset=utf-8');

// Debug logging for incoming requests (remove in production)
error_log('api_labors_parts.php - REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('api_labors_parts.php - raw input: ' . file_get_contents('php://input'));
error_log('api_labors_parts.php - _GET: ' . print_r($_GET, true));
error_log('api_labors_parts.php - _POST: ' . print_r($_POST, true));

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$typeParam = $_GET['type'] ?? null;
$typeParam = in_array($typeParam, ['labors', 'parts']) ? $typeParam : null;

try {
    if ($method === 'GET') {
        // CSV export: /admin/api_labors_parts.php?action=export&type=labors
        if ($action === 'export' && $typeParam) {
            $table = $typeParam;
            $rows = $pdo->query("SELECT id, name, description, default_price, created_by, created_at FROM {$table} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $table . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','name','description','default_price','created_by','created_at']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;
        }

        // Autocomplete suggestions: ?q=term
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $results = [];
            $stmt = $pdo->prepare("SELECT id, name, description, default_price, 'labor' as type FROM labors WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 10");
            $like = "%{$q}%";
            $stmt->execute([$like, $like]);
            $labors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT id, name, description, default_price, 'part' as type FROM parts WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 10");
            $stmt->execute([$like, $like]);
            $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = array_merge($labors, $parts);
            echo json_encode(['success' => true, 'data' => $results]);
            exit;
        }

        // List for a specific type ?type=labor|part
        $listType = $_GET['type'] ?? null;
        if (in_array($listType, ['labor','part'])) {
            $table = $listType === 'part' ? 'parts' : 'labors';
            $rows = $pdo->query("SELECT * FROM $table ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) $data = $_POST;

        $op = $data['action'] ?? '';
        $type = $data['type'] ?? '';
        if (!in_array($type, ['labor','part'])) throw new Exception('Invalid type');
        $table = $type === 'part' ? 'parts' : 'labors';

        if ($op === 'add') {
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $default_price = (float)($data['default_price'] ?? 0);
            if ($name === '') throw new Exception('Name required');
            $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $default_price, $_SESSION['user_id']]);
            $id = $pdo->lastInsertId();
            $row = $pdo->prepare("SELECT * FROM $table WHERE id = ?"); $row->execute([$id]);
            echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($op === 'edit') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $default_price = (float)($data['default_price'] ?? 0);
            if ($name === '') throw new Exception('Name required');
            $stmt = $pdo->prepare("UPDATE $table SET name = ?, description = ?, default_price = ? WHERE id = ?");
            $stmt->execute([$name, $description, $default_price, $id]);
            $row = $pdo->prepare("SELECT * FROM $table WHERE id = ?"); $row->execute([$id]);
            echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($op === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

        throw new Exception('Unknown action');
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}