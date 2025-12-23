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
            // add explicit type to each row so clients can treat results uniformly
            $rows = array_map(function($r) use ($listType){ $r['type'] = $listType === 'part' ? 'part' : 'labor'; return $r; }, $rows);
            // Log row count and a small sample for debugging
            error_log('api_labors_parts.php - GET list ' . $table . ' rows: ' . count($rows));
            if (!empty($_GET['debug'])) {
                error_log('api_labors_parts.php - sample rows: ' . json_encode(array_slice($rows, 0, 5)));
            }
            $response = ['success' => true, 'data' => $rows];
            if (!empty($_GET['debug'])) $response['debug'] = ['table' => $table, 'rows_count' => count($rows)];
            echo json_encode($response);
            exit;
        }

        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $rawInput = file_get_contents('php://input');
        if (!is_array($data)) $data = $_POST;

        $op = $data['action'] ?? '';
        $type = $data['type'] ?? '';
        $debug = !empty($data['debug']);
        if (!in_array($type, ['labor','part'])) throw new Exception('Invalid type');
        $table = $type === 'part' ? 'parts' : 'labors';

        // gather debug info to return when requested
        // Check table existence and basic DB health
        $tablesExist = [];
        foreach (['labors', 'parts'] as $t) {
            try {
                $cstmt = $pdo->prepare("SELECT 1 FROM $t LIMIT 1");
                $cstmt->execute();
                $tablesExist[$t] = true;
            } catch (Exception $te) {
                $tablesExist[$t] = false;
            }
        }
        $debugInfo = ['raw_input' => $rawInput, '_GET' => $_GET, '_POST' => $_POST, 'session_user' => $_SESSION['user_id'] ?? null, 'session_role' => $_SESSION['role'] ?? null, 'tables_exist' => $tablesExist];

        if ($op === 'add') {
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $default_price = (float)($data['default_price'] ?? 0);
            if ($name === '') throw new Exception('Name required');
            try {
                $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $default_price, $_SESSION['user_id']]);
                $id = $pdo->lastInsertId();
                $row = $pdo->prepare("SELECT * FROM $table WHERE id = ?"); $row->execute([$id]);
                $rowData = $row->fetch(PDO::FETCH_ASSOC);
                if ($rowData) $rowData['type'] = $type;
                $response = ['success' => true, 'data' => $rowData];
                if ($debug) $response['debug'] = $debugInfo;
                echo json_encode($response);
                exit;
            } catch (Exception $e) {
                error_log('api_labors_parts.php - Insert error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database insert failed'];
                if ($debug) $response['debug'] = array_merge($debugInfo, ['insert_error' => $e->getMessage()]);
                echo json_encode($response);
                exit;
            }
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
            $rowData = $row->fetch(PDO::FETCH_ASSOC);
            if ($rowData) $rowData['type'] = $type;
            $response = ['success' => true, 'data' => $rowData];
            if ($debug) $response['debug'] = $debugInfo;
            echo json_encode($response);
            exit;
        }

        if ($op === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true];
            if ($debug) $response['debug'] = $debugInfo;
            echo json_encode($response);
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