<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])){
    echo json_encode(['success'=>false,'error'=>'access_denied']); exit;
}
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
try{
    if ($action === 'list'){
        $stmt = $pdo->query('SELECT t.*, u.username as username FROM technicians t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.name');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'technicians'=>$rows]);
        exit;
    }

    if ($action === 'create'){
        $name = trim($_POST['name'] ?? '');
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $email = trim($_POST['email'] ?? '');
        if ($name === '') throw new Exception('Name required');
        $stmt = $pdo->prepare('INSERT INTO technicians (user_id,name,email,created_by) VALUES (?,?,?,?)');
        $stmt->execute([$user_id,$name,$email,$_SESSION['user_id'] ?? null]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]); exit;
    }

    if ($action === 'update'){
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        if (!$id || $name==='') throw new Exception('Invalid');
        $stmt = $pdo->prepare('UPDATE technicians SET user_id=?, name=?, email=? WHERE id=?');
        $stmt->execute([$user_id,$name,$email,$id]);
        echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'delete'){
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Invalid');
        $stmt = $pdo->prepare('DELETE FROM technicians WHERE id=?'); $stmt->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'add_rule'){
        $tech = (int)($_POST['technician_id'] ?? 0);
        $rule_type = $_POST['rule_type'] ?? 'percentage';
        $value = (float)($_POST['value'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if (!$tech) throw new Exception('Invalid');
        $stmt = $pdo->prepare('INSERT INTO payroll_rules (technician_id,rule_type,value,description) VALUES (?,?,?,?)');
        $stmt->execute([$tech,$rule_type,$value,$desc]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]); exit;
    }

    if ($action === 'list_rules'){
        $tech = (int)($_GET['technician_id'] ?? 0);
        if (!$tech) throw new Exception('Invalid');
        $stmt = $pdo->prepare('SELECT * FROM payroll_rules WHERE technician_id=? ORDER BY id');
        $stmt->execute([$tech]); echo json_encode(['success'=>true,'rules'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    if ($action === 'delete_rule'){
        $id = (int)($_POST['id'] ?? 0); if (!$id) throw new Exception('Invalid');
        $stmt = $pdo->prepare('DELETE FROM payroll_rules WHERE id=?'); $stmt->execute([$id]); echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'update_rule'){
        $id = (int)($_POST['id'] ?? 0); $rule_type = $_POST['rule_type'] ?? null; $value = (float)($_POST['value'] ?? 0); $desc = trim($_POST['description'] ?? '');
        if (!$id || !$rule_type) throw new Exception('Invalid');
        $stmt = $pdo->prepare('UPDATE payroll_rules SET rule_type=?, value=?, description=? WHERE id=?'); $stmt->execute([$rule_type, $value, $desc, $id]); echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'compute_earnings'){        // params: technician_id, start_date, end_date
        $tech = (int)($_GET['technician_id'] ?? 0);
        if (!$tech) throw new Exception('Invalid');
        $start = $_GET['start'] ?? null; $end = $_GET['end'] ?? null;
        // Fetch invoices in date range (we will compute per-item assignment to technician)
        $q = 'SELECT id, items, technician_id, creation_date FROM invoices WHERE 1=1';
        $params = [];
        if ($start){ $q .= ' AND creation_date >= ?'; $params[] = $start; }
        if ($end){ $q .= ' AND creation_date <= ?'; $params[] = $end; }
        $stmt = $pdo->prepare($q); $stmt->execute($params); $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalEarned = 0.0; $totalLabor = 0.0; $applied = [];
        // fetch rules for technician
        $r = $pdo->prepare('SELECT * FROM payroll_rules WHERE technician_id=?'); $r->execute([$tech]); $rules = $r->fetchAll(PDO::FETCH_ASSOC);
        // fetch technician name for fallback matching (older invoices may store technician by name)
        $stmt = $pdo->prepare('SELECT name FROM technicians WHERE id=? LIMIT 1'); $stmt->execute([$tech]); $techRow = $stmt->fetch(PDO::FETCH_ASSOC); $techName = $techRow ? trim($techRow['name']) : '';

        foreach($invoices as $inv){
            $items = json_decode($inv['items'] ?? '[]', true);
            $laborSumForTech = 0.0;
            $isTechTagged = false; // Flag to check if tech is on at least one item
            foreach($items as $it){
                // Determine whether this item should be considered 'labor'
                $isLabor = false;
                if (isset($it['type']) && $it['type'] === 'labor') $isLabor = true;
                if (isset($it['db_type']) && $it['db_type'] === 'labor') $isLabor = true;
                if (array_key_exists('price_svc', $it)) $isLabor = true; // presence of price_svc indicates a labor line (even if 0)
                if (!$isLabor) continue;

                $assignedTechId = isset($it['tech_id']) ? (int)$it['tech_id'] : 0;
                $assignedTechName = isset($it['tech']) ? trim($it['tech']) : '';

                // Match per-item assigned tech id OR per-item tech name fallback
                $isMatch = ($assignedTechId === $tech) || (!empty($techName) && !empty($assignedTechName) && strtolower($assignedTechName) === strtolower($techName));

                if ($isMatch) {
                    $isTechTagged = true; // Tech is tagged on this invoice
                    $line = 0.0;
                    if (isset($it['line_total'])) {
                        $line = floatval($it['line_total']);
                    } elseif (isset($it['price_svc'])) {
                        $qty = isset($it['qty']) ? floatval($it['qty']) : 1;
                        $price = floatval($it['price_svc']);
                        $discount = isset($it['discount_svc']) ? floatval($it['discount_svc']) : 0.0;
                        $line = ($price * (1.0 - ($discount / 100.0))) * $qty;
                    } elseif (isset($it['price'])) {
                        $qty = isset($it['qty']) ? floatval($it['qty']) : 1;
                        $line = floatval($it['price']) * $qty;
                    }
                    $laborSumForTech += $line;
                }
            }
            
            // Skip invoice if technician is not tagged on any item
            if (!$isTechTagged) {
                continue;
            }

            // Apply global service discount (if any) to technician's labor proportionally
            $serviceDiscountPercent = isset($inv['service_discount_percent']) ? floatval($inv['service_discount_percent']) : 0.0;
            $globalFactor = max(0.0, 1.0 - ($serviceDiscountPercent / 100.0));

            // Labor after applying global service discount
            $laborAfterGlobal = $laborSumForTech * $globalFactor;

            // apply rules to this invoice's technician-specific labor (after global discount)
            $invoiceEarnings = 0.0;
            foreach($rules as $rule){
                if ($rule['rule_type'] === 'percentage'){
                    $invoiceEarnings += ($rule['value'] / 100.0) * $laborAfterGlobal;
                } else if ($rule['rule_type'] === 'fixed_per_invoice'){
                    // apply fixed per invoice only if technician had labor on that invoice
                    if ($laborAfterGlobal > 0) $invoiceEarnings += floatval($rule['value']);
                }
            }

            // Record detailed applied info (include raw and post-discount labor for transparency)
            $applied[] = ['invoice_id'=>$inv['id'],'labor_raw'=>round($laborSumForTech,2),'service_discount_percent'=>$serviceDiscountPercent,'labor_after_discount'=>round($laborAfterGlobal,2),'earnings'=>round($invoiceEarnings,2)];

            // Tally totals (use post-global-discount labor for totals)
            $totalLabor += $laborAfterGlobal;
            $totalEarned += $invoiceEarnings;
        }

        $invoiceCountWithLabor = count($applied); // Count only the invoices that were processed
        echo json_encode(['success'=>true,'total_earned'=>round($totalEarned,2),'total_labor'=>round($totalLabor,2),'invoice_count'=>$invoiceCountWithLabor,'details'=>$applied]); exit;
    }

    echo json_encode(['success'=>false,'error'=>'unknown_action']);
} catch (Exception $e){ echo json_encode(['success'=>false,'error'=>'exception','message'=>$e->getMessage()]); }
