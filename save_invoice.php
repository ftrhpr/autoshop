<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST;

    // Process items
    $items = [];
    for ($i = 0; isset($data["item_name_$i"]); $i++) {
        if (!empty($data["item_name_$i"])) {
            $items[] = [
                'name' => $data["item_name_$i"],
                'qty' => $data["item_qty_$i"],
                'price_part' => $data["item_price_part_$i"],
                'price_svc' => $data["item_price_svc_$i"],
                'tech' => $data["item_tech_$i"]
            ];
        }
    }

    // Prefer customer_id if provided
    $customer_id = null;
    if (!empty($data['customer_id'])) {
        $customer_id = (int)$data['customer_id'];
        // Update customer using provided invoice data
        $stmt = $pdo->prepare('UPDATE customers SET full_name = ?, phone = ?, car_mark = ? WHERE id = ?');
        $stmt->execute([$data['customer_name'], $data['phone_number'], $data['car_mark'], $customer_id]);
    } else {
        // Try to find or create customer by plate number or phone
        $plate = strtoupper(trim($data['plate_number'] ?? ''));
        $phone = trim($data['phone_number'] ?? '');

        if ($plate !== '') {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE plate_number = ? LIMIT 1');
            $stmt->execute([$plate]);
            $found = $stmt->fetch();
            if ($found) {
                $customer_id = $found['id'];
                $stmt = $pdo->prepare('UPDATE customers SET full_name = ?, phone = ?, car_mark = ? WHERE id = ?');
                $stmt->execute([$data['customer_name'], $data['phone_number'], $data['car_mark'], $customer_id]);
            } else {
                if (trim($data['customer_name']) !== '') {
                    $stmt = $pdo->prepare('INSERT INTO customers (full_name, phone, plate_number, car_mark, created_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$data['customer_name'], $data['phone_number'], $plate, $data['car_mark'], $_SESSION['user_id']]);
                    $customer_id = $pdo->lastInsertId();
                }
            }
        } elseif ($phone !== '') {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            $found = $stmt->fetch();
            if ($found) {
                $customer_id = $found['id'];
                $stmt = $pdo->prepare('UPDATE customers SET full_name = ?, car_mark = ? WHERE id = ?');
                $stmt->execute([$data['customer_name'], $data['car_mark'], $customer_id]);
            } else {
                if (trim($data['customer_name']) !== '') {
                    $stmt = $pdo->prepare('INSERT INTO customers (full_name, phone, plate_number, car_mark, created_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$data['customer_name'], $data['phone_number'], $plate, $data['car_mark'], $_SESSION['user_id']]);
                    $customer_id = $pdo->lastInsertId();
                }
            }
        }
    }
    // Resolve service manager display name when a user id is provided
    $serviceManagerName = $data['service_manager'] ?? '';
    if (!empty($data['service_manager_id'])) {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$data['service_manager_id']]);
        $um = $stmt->fetch();
        if ($um) $serviceManagerName = $um['username'];
    }

    // Ensure service manager defaults to current logged-in user if empty
    if (empty($serviceManagerName) && !empty($_SESSION['username'])) {
        $serviceManagerName = $_SESSION['username'];
    }

    // Calculate totals from items if not provided or invalid
    $partsTotal = 0.00;
    $serviceTotal = 0.00;
    $grandTotal = 0.00;

    foreach ($items as $item) {
        $qty = (float)($item['qty'] ?? 0);
        $pricePart = (float)($item['price_part'] ?? 0);
        $priceSvc = (float)($item['price_svc'] ?? 0);

        $partsTotal += $qty * $pricePart;
        $serviceTotal += $qty * $priceSvc;
    }
    $grandTotal = $partsTotal + $serviceTotal;

    // Use calculated values if POST values are empty or invalid
    $providedPartsTotal = isset($data['parts_total']) && is_numeric($data['parts_total']) ? (float)$data['parts_total'] : null;
    $providedServiceTotal = isset($data['service_total']) && is_numeric($data['service_total']) ? (float)$data['service_total'] : null;
    $providedGrandTotal = isset($data['grand_total']) && is_numeric($data['grand_total']) ? (float)$data['grand_total'] : null;

    // Use provided values if they match calculations (within small tolerance), otherwise use calculated
    $finalPartsTotal = ($providedPartsTotal !== null && abs($providedPartsTotal - $partsTotal) < 0.01) ? $providedPartsTotal : $partsTotal;
    $finalServiceTotal = ($providedServiceTotal !== null && abs($providedServiceTotal - $serviceTotal) < 0.01) ? $providedServiceTotal : $serviceTotal;
    $finalGrandTotal = ($providedGrandTotal !== null && abs($providedGrandTotal - $grandTotal) < 0.01) ? $providedGrandTotal : $grandTotal;

    $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, service_manager, service_manager_id, customer_id, customer_name, phone, car_mark, plate_number, mileage, items, parts_total, service_total, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['creation_date'],
        $serviceManagerName,
        !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
        $customer_id,
        $data['customer_name'],
        $data['phone_number'],
        $data['car_mark'],
        $data['plate_number'],
        $data['mileage'],
        json_encode($items),
        $finalPartsTotal,
        $finalServiceTotal,
        $finalGrandTotal,
        $_SESSION['user_id']
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Redirect based on flag
    if (!empty($data['print_after_save'])) {
        header('Location: print_invoice.php?id=' . $invoice_id);
    } else {
        header('Location: view_invoice.php?id=' . $invoice_id);
    }
    exit;
}
?>