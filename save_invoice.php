<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST;
    $existing_id = isset($data['existing_invoice_id']) ? (int)$data['existing_invoice_id'] : null;

    // Process itemss
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

    // Handle customer - always create new customer if none selected
    $customer_id = null;
    $was_selected = !empty($data['customer_id']);
    if ($was_selected) {
        $customer_id = (int)$data['customer_id'];
        // Verify the customer still exists
        $stmt = $pdo->prepare('SELECT full_name, phone, plate_number, car_mark FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$customer_id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            error_log("Selected customer ID $customer_id no longer exists");
            throw new Exception('The selected customer no longer exists. Please select a different customer or create a new one.');
        }
        // If any info changed, treat as new customer
        $currentName = trim($existing['full_name']);
        $newName = trim($data['customer_name']);
        $currentPhone = preg_replace('/\s+/', '', trim($existing['phone']));
        $newPhone = preg_replace('/\s+/', '', trim($data['phone_number']));
        $currentPlate = strtoupper(trim($existing['plate_number']));
        $newPlate = strtoupper(trim($data['plate_number'] ?? ''));
        $currentCar = trim($existing['car_mark']);
        $newCar = trim($data['car_mark']);
        if ($currentName !== $newName || $currentPhone !== $newPhone || $currentPlate !== $newPlate || $currentCar !== $newCar) {
            $customer_id = null; // Will create new below
        }
        // No update for existing customer
    }

    // Now handle creation if needed
    if ($customer_id === null) {
        $plateNumber = strtoupper(trim($data['plate_number'] ?? ''));
        if (trim($data['customer_name']) !== '' && !empty($plateNumber)) {
            if ($was_selected) {
                // Create new customer directly since selected was changed
                $stmt = $pdo->prepare('INSERT INTO customers (full_name, phone, plate_number, car_mark, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $data['customer_name'],
                    $data['phone_number'],
                    $plateNumber,
                    $data['car_mark'],
                    $_SESSION['user_id']
                ]);
                $customer_id = $pdo->lastInsertId();
                error_log("Created new customer ID $customer_id because selected was changed");
            } else {
                // Check if customer with this plate number already exists
                $stmt = $pdo->prepare('SELECT id FROM customers WHERE plate_number = ? LIMIT 1');
                $stmt->execute([$plateNumber]);
                $existingCustomer = $stmt->fetch();

                if ($existingCustomer) {
                    // Use existing customer
                    $customer_id = $existingCustomer['id'];
                    error_log("Used existing customer ID $customer_id for plate $plateNumber");
                } else {
                    // Create new customer
                    $stmt = $pdo->prepare('INSERT INTO customers (full_name, phone, plate_number, car_mark, created_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $data['customer_name'],
                        $data['phone_number'],
                        $plateNumber,
                        $data['car_mark'],
                        $_SESSION['user_id']
                    ]);
                    $customer_id = $pdo->lastInsertId();
                    error_log("Created new customer ID $customer_id for plate $plateNumber");
                }
            }
        } elseif (trim($data['customer_name']) !== '') {
            // Customer name provided but no plate number - this should not happen due to frontend validation
            error_log("Attempted to save customer without plate number: " . json_encode($data));
            throw new Exception('Plate number is required when creating a new customer. Please refresh the page and try again.');
        }
        // If no customer name, customer_id remains null (invoice without customer)
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

    // Validate customer_id exists if provided
    if ($customer_id !== null) {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$customer_id]);
        if (!$stmt->fetch()) {
            error_log("Customer ID $customer_id does not exist in database");
            throw new Exception('Invalid customer reference. Please try again.');
        }
    }

    $vin = trim($data['vin'] ?? '');

    if ($existing_id) {
        // Update existing invoice (include VIN)
        $stmt = $pdo->prepare("UPDATE invoices SET creation_date = ?, service_manager = ?, service_manager_id = ?, customer_id = ?, customer_name = ?, phone = ?, car_mark = ?, plate_number = ?, vin = ?, mileage = ?, items = ?, parts_total = ?, service_total = ?, grand_total = ? WHERE id = ?");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $customer_id,
            $data['customer_name'],
            $data['phone_number'],
            $data['car_mark'],
            $data['plate_number'],
            $vin,
            $data['mileage'],
            json_encode($items),
            $finalPartsTotal,
            $finalServiceTotal,
            $finalGrandTotal,
            $existing_id
        ]);
        $invoice_id = $existing_id;
    } else {
        // Insert new invoice (include VIN)
        $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, service_manager, service_manager_id, customer_id, customer_name, phone, car_mark, plate_number, vin, mileage, items, parts_total, service_total, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $customer_id,
            $data['customer_name'],
            $data['phone_number'],
            $data['car_mark'],
            $data['plate_number'],
            $vin,
            $data['mileage'],
            json_encode($items),
            $finalPartsTotal,
            $finalServiceTotal,
            $finalGrandTotal,
            $_SESSION['user_id']
        ]);
        $invoice_id = $pdo->lastInsertId();
    }

    // Process uploaded images (if any)
    if (!empty($_FILES['images'])) {
        $uploaded = $_FILES['images'];
        $stored = [];
        $uploadDir = __DIR__ . '/uploads/invoices/' . $invoice_id;
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        // Normalize structure
        $fileCount = is_array($uploaded['name']) ? count($uploaded['name']) : 0;
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($uploaded['name'][$i])) continue;
            if ($uploaded['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $uploaded['tmp_name'][$i];
            $orig = basename($uploaded['name'][$i]);
            // Sanitize filename
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $filename = $base . '_' . time() . '_' . $i . '.' . $ext;
            $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                // store relative path for web
                $rel = 'uploads/invoices/' . $invoice_id . '/' . $filename;
                $stored[] = $rel;
            }
        }

        if (!empty($stored)) {
            try {
                // Fetch existing images
                $stmt = $pdo->prepare('SELECT images FROM invoices WHERE id = ? LIMIT 1');
                $stmt->execute([$invoice_id]);
                $row = $stmt->fetch();
                $existingImages = $row && !empty($row['images']) ? json_decode($row['images'], true) : [];
                $merged = array_values(array_filter(array_merge($existingImages ?: [], $stored)));
                $stmt = $pdo->prepare('UPDATE invoices SET images = ? WHERE id = ?');
                $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), $invoice_id]);
            } catch (PDOException $e) {
                // If column doesn't exist or update fails, log and continue
                error_log('Failed to save uploaded images for invoice ' . $invoice_id . ': ' . $e->getMessage());
            }
        }
    }

    // Redirect based on flag
    if (!empty($data['print_after_save'])) {
        header('Location: print_invoice.php?id=' . $invoice_id);
    } else {
        header('Location: view_invoice.php?id=' . $invoice_id);
    }
    exit;
}
?>