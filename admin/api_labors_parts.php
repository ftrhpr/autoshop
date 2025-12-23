<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($method === 'GET') {
        // CSV export: /admin/api_labors_parts.php?action=export&type=labors
        $action = $_GET['action'] ?? null;
        $typeParam = $_GET['type'] ?? null;
        $typeParam = in_array($typeParam, ['labors','parts']) ? $typeParam : null;

        if ($action === 'export' && $typeParam) {
            $table = $typeParam;
            $rows = $pdo->query("SELECT id, name, description, default_price, vehicle_make_model, created_by, created_at FROM {$table} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $table . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','name','description','default_price','vehicle_make_model','created_by','created_at']);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit;
        }

        // Price list for a specific item: ?action=prices&item_id=123&item_type=part|labor
        if ($action === 'prices' && !empty($_GET['item_id'])) {
            $itemId = (int)$_GET['item_id'];
            $itemType = in_array($_GET['item_type'] ?? '', ['part','labor']) ? $_GET['item_type'] : null;
            if (!$itemType) {
                // Determine item_type from item_id
                $stmt = $pdo->prepare("SELECT 'part' AS type FROM parts WHERE id = ? UNION SELECT 'labor' AS type FROM labors WHERE id = ? LIMIT 1");
                $stmt->execute([$itemId, $itemId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $itemType = $row['type'];
                else { echo json_encode(['success' => false, 'message' => 'Invalid item_id']); exit; }
            }
            $ps = $pdo->prepare('SELECT id, vehicle_make_model, price, created_by, created_at FROM item_prices WHERE item_type = ? AND item_id = ? ORDER BY vehicle_make_model');
            $ps->execute([$itemType, $itemId]);
            $prices = $ps->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $prices]);
            exit;
        }

        // Autocomplete suggestions: ?q=term&vehicle=Make%20Model
        $q = trim($_GET['q'] ?? '');
        $vehicle = trim($_GET['vehicle'] ?? '');
        if ($q !== '') {
            $results = [];
            $like = "%{$q}%";

            if ($vehicle !== '') {
                $vLower = strtolower($vehicle);
                $vLike = "%{$vLower}%";
                // Include all matching labors but prioritize by: exact vehicle match -> vehicle contains typed -> typed contains vehicle -> others
                $stmt = $pdo->prepare("SELECT id, name, description, default_price, vehicle_make_model, 'labor' as type FROM labors WHERE (name LIKE ? OR description LIKE ?) ORDER BY CASE WHEN LOWER(vehicle_make_model) = ? THEN 0 WHEN LOWER(vehicle_make_model) LIKE ? THEN 1 WHEN LOWER(?) LIKE CONCAT('%', LOWER(vehicle_make_model), '%') THEN 2 ELSE 3 END, name LIMIT 10");
                $stmt->execute([$like, $like, $vLower, $vLike, $vLower]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, description, default_price, vehicle_make_model, 'labor' as type FROM labors WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 10");
                $stmt->execute([$like, $like]);
            }
            $labors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($vehicle !== '') {
                $vLower = strtolower($vehicle);
                $vLike = "%{$vLower}%";
                // Include all matching parts but prioritize by: exact vehicle match -> vehicle contains typed -> typed contains vehicle -> others
                $stmt = $pdo->prepare("SELECT id, name, description, default_price, vehicle_make_model, 'part' as type FROM parts WHERE (name LIKE ? OR description LIKE ?) ORDER BY CASE WHEN LOWER(vehicle_make_model) = ? THEN 0 WHEN LOWER(vehicle_make_model) LIKE ? THEN 1 WHEN LOWER(?) LIKE CONCAT('%', LOWER(vehicle_make_model), '%') THEN 2 ELSE 3 END, name LIMIT 10");
                $stmt->execute([$like, $like, $vLower, $vLike, $vLower]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, description, default_price, vehicle_make_model, 'part' as type FROM parts WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 10");
                $stmt->execute([$like, $like]);
            }
            $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Attach suggested_price from item_prices when vehicle specified; fallback to default_price
            if ($vehicle !== '') {
                // Helper closure to find best vehicle price using smart matching
                $findVehiclePrice = function($itemType, $itemId, $vehicleStr) use ($pdo) {
                    $vLower = strtolower(trim($vehicleStr));
                    // Exact match
                    $s = $pdo->prepare("SELECT price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1");
                    $s->execute([$itemType, $itemId, $vLower]);
                    $row = $s->fetch(PDO::FETCH_ASSOC);
                    if ($row) return $row;
                    // Containing full string
                    $s = $pdo->prepare("SELECT price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1");
                    $s->execute([$itemType, $itemId, "%{$vLower}%"]);
                    $row = $s->fetch(PDO::FETCH_ASSOC);
                    if ($row) return $row;
                    // Token OR matching: check if any token from typed value matches stored vehicle (e.g., typed 'Audi Q5' matches stored 'Q5')
                    $tokens = preg_split('/\s+/', $vLower);
                    foreach ($tokens as $t) {
                        $t = trim($t); if ($t === '') continue;
                        $s = $pdo->prepare("SELECT price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1");
                        $s->execute([$itemType, $itemId, "%{$t}%"]);
                        $row = $s->fetch(PDO::FETCH_ASSOC);
                        if ($row) return $row;
                    }
                    // Token AND matching (all words present)
                    $ands = [];
                    $params = [$itemType, $itemId];
                    foreach ($tokens as $t) { if (trim($t) === '') continue; $ands[] = 'LOWER(vehicle_make_model) LIKE ?'; $params[] = "%{$t}%"; }
                    if (!empty($ands)) {
                        $sql = 'SELECT price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND ' . implode(' AND ', $ands) . ' ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1';
                        $s = $pdo->prepare($sql);
                        $s->execute($params);
                        $row = $s->fetch(PDO::FETCH_ASSOC);
                        if ($row) return $row;
                    }
                    return false;
                };

                foreach ($labors as &$l) {
                    $pv = $findVehiclePrice('labor', $l['id'], $vehicle);
                    if ($pv) {
                        $l['suggested_price'] = (float)$pv['price'];
                        $l['has_vehicle_price'] = true;
                        $l['vehicle_make_model'] = $pv['vehicle_make_model'];
                    } else {
                        $l['suggested_price'] = (float)$l['default_price'];
                        $l['has_vehicle_price'] = false;
                    }
                }
                unset($l);

                foreach ($parts as &$p) {
                    $pv = $findVehiclePrice('part', $p['id'], $vehicle);
                    if ($pv) {
                        $p['suggested_price'] = (float)$pv['price'];
                        $p['has_vehicle_price'] = true;
                        $p['vehicle_make_model'] = $pv['vehicle_make_model'];
                    } else {
                        $p['suggested_price'] = (float)$p['default_price'];
                        $p['has_vehicle_price'] = false;
                    }
                }
                unset($p);
            } else {
                foreach ($labors as &$l) { $l['suggested_price'] = (float)$l['default_price']; $l['has_vehicle_price'] = false; } unset($l);
                foreach ($parts as &$p) { $p['suggested_price'] = (float)$p['default_price']; $p['has_vehicle_price'] = false; } unset($p);
            }

            $results = array_merge($labors, $parts);
            echo json_encode(['success' => true, 'data' => $results]);
            exit;
        }

        // List for a specific type ?type=labor|part
        $listType = $_GET['type'] ?? null;
        if (in_array($listType, ['labor','part'])) {
            $table = $listType === 'part' ? 'parts' : 'labors';
            $rows = $pdo->query("SELECT * FROM $table ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            // add explicit type
            $rows = array_map(function($r) use ($listType){ $r['type'] = $listType === 'part' ? 'part' : 'labor'; return $r; }, $rows);
            echo json_encode(['success' => true, 'data' => $rows]);
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

        if ($op === 'add') {
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $default_price = (float)($data['default_price'] ?? 0);
            $vehicle = trim($data['vehicle_make_model'] ?? $data['vehicle'] ?? '');
            if ($name === '') throw new Exception('Name required');

            // Decide target table(s) based on type param or price values
            $typesToCreate = [];
            if (in_array($type, ['part','labor'])) $typesToCreate = [$type];
            else {
                // If not specified, create part if default_price provided, create labor if svc_price provided (caller should specify)
                $typesToCreate = ['part']; // default
            }

            $responses = [];
            foreach ($typesToCreate as $t) {
                $table = $t === 'part' ? 'parts' : 'labors';
                $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by, vehicle_make_model) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $default_price, $_SESSION['user_id'], $vehicle ?: NULL]);
                $id = $pdo->lastInsertId();
                $row = $pdo->prepare("SELECT * FROM $table WHERE id = ?"); $row->execute([$id]);
                $rowData = $row->fetch(PDO::FETCH_ASSOC);
                if ($rowData) $rowData['type'] = $t;
                $responses[] = $rowData;
            }

            $response = ['success' => true, 'data' => $responses];
            if ($debug) $response['debug'] = ['raw_input' => $rawInput, 'session_user' => $_SESSION['user_id'] ?? null];
            echo json_encode($response);
            exit;
        }

        if ($op === 'edit') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $default_price = (float)($data['default_price'] ?? 0);
            $vehicle = trim($data['vehicle_make_model'] ?? $data['vehicle'] ?? '');
            $table = in_array($data['type'] ?? '', ['part','labor']) && $data['type']==='part' ? 'parts' : 'labors';
            $stmt = $pdo->prepare("UPDATE $table SET name = ?, description = ?, default_price = ?, vehicle_make_model = ? WHERE id = ?");
            $stmt->execute([$name, $description, $default_price, $vehicle ?: NULL, $id]);
            $row = $pdo->prepare("SELECT * FROM $table WHERE id = ?"); $row->execute([$id]);
            $rowData = $row->fetch(PDO::FETCH_ASSOC);
            if ($rowData) $rowData['type'] = $data['type'] ?? ($table === 'parts' ? 'part' : 'labor');
            echo json_encode(['success' => true, 'data' => $rowData]);
            exit;
        }

        if ($op === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $table = $data['type'] === 'part' ? 'parts' : 'labors';
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

        // Price management operations
        if ($op === 'price_add') {
            $itemId = (int)($data['item_id'] ?? 0);
            $itemType = in_array($data['item_type'] ?? '', ['part','labor']) ? $data['item_type'] : null;
            $vehicle = trim($data['vehicle_make_model'] ?? $data['vehicle'] ?? '');
            $price = (float)($data['price'] ?? 0);
            if (!$itemType && $itemId > 0) {
                // Determine item_type from item_id
                $stmt = $pdo->prepare("SELECT 'part' AS type FROM parts WHERE id = ? UNION SELECT 'labor' AS type FROM labors WHERE id = ? LIMIT 1");
                $stmt->execute([$itemId, $itemId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $itemType = $row['type'];
            }
            if (!$itemId || !$itemType || $vehicle === '' || $price <= 0) throw new Exception('Missing or invalid parameters');
            $ins = $pdo->prepare("INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP");
            $ins->execute([$itemType, $itemId, $vehicle, $price, $_SESSION['user_id']]);
            $row = $pdo->prepare('SELECT * FROM item_prices WHERE item_type = ? AND item_id = ? AND vehicle_make_model = ? LIMIT 1'); $row->execute([$itemType, $itemId, $vehicle]);
            echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($op === 'price_edit') {
            $id = (int)($data['id'] ?? 0);
            $price = (float)($data['price'] ?? 0);
            $vehicle = trim($data['vehicle_make_model'] ?? $data['vehicle'] ?? '');
            if ($id <= 0) throw new Exception('Invalid price id');
            $stmt = $pdo->prepare('UPDATE item_prices SET price = ?, vehicle_make_model = ? WHERE id = ?');
            $stmt->execute([$price, $vehicle, $id]);
            $row = $pdo->prepare('SELECT * FROM item_prices WHERE id = ? LIMIT 1'); $row->execute([$id]);
            echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($op === 'price_delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid price id');
            $stmt = $pdo->prepare('DELETE FROM item_prices WHERE id = ?');
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