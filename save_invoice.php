<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST;
    $existing_id = isset($data['existing_invoice_id']) ? (int)$data['existing_invoice_id'] : null;

    // Process items
    $items = [];
    for ($i = 0; isset($data["item_name_$i"]); $i++) {
        $name = trim($data["item_name_$i"] ?? '');
        if ($name !== '') {
            $items[] = [
                'name' => $name,
                'qty' => $data["item_qty_$i"],
                'price_part' => $data["item_price_part_$i"],
                'price_svc' => $data["item_price_svc_$i"],
                'tech' => $data["item_tech_$i"],
                // optional matched DB info from autocomplete
                'db_id' => isset($data["item_db_id_$i"]) ? (int)$data["item_db_id_$i"] : null,
                'db_type' => isset($data["item_db_type_$i"]) ? $data["item_db_type_$i"] : null,
            ];
        }
    }

    // Handle vehicle - find or create for existing customer
    $vehicle_id = null;
    $was_selected = !empty($data['vehicle_id']);
    if ($was_selected) {
        $vehicle_id = (int)$data['vehicle_id'];
        // Verify the vehicle still exists
        $stmt = $pdo->prepare('SELECT v.id FROM vehicles v WHERE v.id = ? LIMIT 1');
        $stmt->execute([$vehicle_id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            error_log("Selected vehicle ID $vehicle_id no longer exists");
            throw new Exception('The selected vehicle no longer exists. Please select a different vehicle.');
        }
    }

    // Now handle creation if needed
    if ($vehicle_id === null) {
        $plateNumber = strtoupper(trim($data['plate_number'] ?? ''));
        $customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;

        if (!empty($customer_id) && !empty($plateNumber)) {
            // If a customer is explicitly selected, prefer adding the vehicle to that customer
            $stmt = $pdo->prepare('SELECT v.id, v.customer_id FROM vehicles v WHERE v.plate_number = ? LIMIT 1');
            $stmt->execute([$plateNumber]);
            $existingVehicle = $stmt->fetch();

            if ($existingVehicle) {
                $vehicle_id = $existingVehicle['id'];
                error_log("Used existing vehicle ID $vehicle_id for plate $plateNumber");

                // Ensure customer main record kept in sync
                $stmt = $pdo->prepare('UPDATE customers SET plate_number = ?, car_mark = ? WHERE id = ?');
                $stmt->execute([$plateNumber, $data['car_mark'], $existingVehicle['customer_id']]);
            } else {
                // Add new vehicle for selected customer
                $stmt = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, car_mark, vin, mileage) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $customer_id,
                    $plateNumber,
                    $data['car_mark'],
                    $data['vin'] ?? '',
                    $data['mileage'] ?? ''
                ]);
                $vehicle_id = $pdo->lastInsertId();
                error_log("Added new vehicle ID $vehicle_id for existing customer ID {$customer_id}, plate $plateNumber");

                // Update customer record with latest vehicle info (backwards compatibility)
                $stmt = $pdo->prepare('UPDATE customers SET plate_number = ?, car_mark = ? WHERE id = ?');
                $stmt->execute([$plateNumber, $data['car_mark'], $customer_id]);

                // Audit
                $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], 'create_vehicle_from_invoice', "vehicle_id={$vehicle_id}, customer_id={$customer_id}, plate={$plateNumber}", $_SERVER['REMOTE_ADDR'] ?? '']);
            }

        } elseif (trim($data['customer_name']) !== '' && !empty($plateNumber)) {
            // Backwards-compatible flow: find customer by name/phone then add vehicle
            $stmt = $pdo->prepare('SELECT v.id, v.customer_id FROM vehicles v WHERE v.plate_number = ? LIMIT 1');
            $stmt->execute([$plateNumber]);
            $existingVehicle = $stmt->fetch();

            if ($existingVehicle) {
                // Use existing vehicle
                $vehicle_id = $existingVehicle['id'];
                error_log("Used existing vehicle ID $vehicle_id for plate $plateNumber");

                // Ensure customer main record kept in sync with vehicle (plate/car)
                $stmt = $pdo->prepare('UPDATE customers SET plate_number = ?, car_mark = ? WHERE id = ?');
                $stmt->execute([$plateNumber, $data['car_mark'], $existingVehicle['customer_id']]);
            } else {
                // Find customer by name and phone
                $stmt = $pdo->prepare('SELECT id FROM customers WHERE full_name = ? AND phone = ? LIMIT 1');
                $stmt->execute([trim($data['customer_name']), trim($data['phone_number'])]);
                $existingCustomer = $stmt->fetch();

                if ($existingCustomer) {
                    // Add new vehicle to existing customer
                    $stmt = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, car_mark, vin, mileage) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $existingCustomer['id'],
                        $plateNumber,
                        $data['car_mark'],
                        $data['vin'] ?? '',
                        $data['mileage'] ?? ''
                    ]);
                    $vehicle_id = $pdo->lastInsertId();
                    error_log("Added new vehicle ID $vehicle_id for existing customer ID {$existingCustomer['id']}, plate $plateNumber");

                    // Update customer record with latest vehicle info (backwards compatibility)
                    $stmt = $pdo->prepare('UPDATE customers SET plate_number = ?, car_mark = ? WHERE id = ?');
                    $stmt->execute([$plateNumber, $data['car_mark'], $existingCustomer['id']]);

                    // Audit log for vehicle created from invoice
                    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$_SESSION['user_id'], 'create_vehicle_from_invoice', "vehicle_id={$vehicle_id}, customer_id={$existingCustomer['id']}, plate={$plateNumber}", $_SERVER['REMOTE_ADDR'] ?? '']);
                } else {
                    throw new Exception('Customer not found. Please ensure the customer exists or contact admin to add the customer first.');
                }
            }
        } else {
            throw new Exception('Please select a vehicle or provide customer name, phone, and plate number.');
        }
    }

    // If we have a vehicle id, resolve customer/vehicle fields from DB for consistency
    if (!empty($vehicle_id)) {
        $stmt = $pdo->prepare('SELECT v.*, c.id as customer_id, c.full_name, c.phone FROM vehicles v JOIN customers c ON v.customer_id = c.id WHERE v.id = ? LIMIT 1');
        $stmt->execute([$vehicle_id]);
        $rec = $stmt->fetch();
        if ($rec) {
            $data['customer_name'] = $rec['full_name'];
            $data['phone_number'] = $rec['phone'];
            $data['car_mark'] = $rec['car_mark'] ?? ($data['car_mark'] ?? '');
            $data['vin'] = $rec['vin'] ?? ($data['vin'] ?? '');
            $data['mileage'] = $rec['mileage'] ?? ($data['mileage'] ?? '');
            $data['customer_id'] = $rec['customer_id'];
        }
    }

    // Ensure invoice items that reference DB entries exist in the parts/labors tables; if not, create them and attach vehicle make/model
    $vehicleMake = trim($data['car_mark'] ?? '');
    $created_items = [];
    foreach ($items as $idx => &$it) {
        $name = trim($it['name'] ?? '');
        if ($name === '') continue;

        // If autocomplete already provided db info, ensure the referenced record exists
        if (!empty($it['db_id']) && !empty($it['db_type'])) {
            // Verify existence and fetch vehicle_make_model
            if ($it['db_type'] === 'part') {
                $v = $pdo->prepare('SELECT id, vehicle_make_model FROM parts WHERE id = ? LIMIT 1'); $v->execute([$it['db_id']]);
                $vv = $v->fetch();
                if (!$vv) { $it['db_id'] = null; $it['db_type'] = null; } else { $it['db_vehicle'] = $vv['vehicle_make_model'] ?? null; }
            } else {
                $v = $pdo->prepare('SELECT id, vehicle_make_model FROM labors WHERE id = ? LIMIT 1'); $v->execute([$it['db_id']]);
                $vv = $v->fetch();
                if (!$vv) { $it['db_id'] = null; $it['db_type'] = null; } else { $it['db_vehicle'] = $vv['vehicle_make_model'] ?? null; }
            }
        }

        // Helper: find existing part/labor by name (do NOT create new items here). If found and vehicle-specific price exists or invoice provides a price, store/attach vehicle-specific price entry.
        if (empty($it['db_id'])) {
            $found = null;
            // If part price provided, prefer matching parts by name
            if (!empty($it['price_part']) && floatval($it['price_part']) > 0) {
                if ($vehicleMake !== '') {
                    $vLower = strtolower($vehicleMake);
                    $vLike = "%{$vLower}%";
                    $stmt = $pdo->prepare("SELECT * FROM parts WHERE name = ? AND ((vehicle_make_model IS NOT NULL AND LOWER(vehicle_make_model) LIKE ?) OR vehicle_make_model IS NULL) ORDER BY CASE WHEN LOWER(vehicle_make_model) = ? THEN 0 WHEN LOWER(vehicle_make_model) LIKE ? THEN 1 ELSE 2 END LIMIT 1");
                    $stmt->execute([$name, $vLike, $vLower, $vLike]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM parts WHERE name = ? LIMIT 1");
                    $stmt->execute([$name]);
                }
                $found = $stmt->fetch();
                if ($found) {
                    $it['db_id'] = $found['id'];
                    $it['db_type'] = 'part';
                    $it['db_vehicle'] = $found['vehicle_make_model'] ?? null;
                    if (empty($it['price_part']) && !empty($found['default_price'])) $it['price_part'] = $found['default_price'];

                    // If vehicle provided, check item_prices for existing vehicle price and use it; otherwise, if invoice contains price, create price entry
                    if ($vehicleMake !== '') {
                        // Smart lookup: try exact match first, then token-based matching (e.g., 'Corolla' matches 'Toyota Corolla')
                        $pv = false;
                        $vLower = strtolower(trim($vehicleMake));

                        $pvStmt = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1');
                        $pvStmt->execute(['part', $it['db_id'], $vLower]);
                        $pv = $pvStmt->fetch();

                        if (!$pv) {
                            // Try containing full string
                            $pvStmt2 = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                            $pvStmt2->execute(['part', $it['db_id'], "%{$vLower}%"]);
                            $pv = $pvStmt2->fetch();
                        }

                        if (!$pv) {
                            // Try token AND-matching (all tokens must appear in vehicle_make_model)
                            $tokens = preg_split('/\s+/', $vLower);
                            $ands = [];
                            $params = ['part', $it['db_id']];
                            foreach ($tokens as $t) { if (trim($t) === '') continue; $ands[] = 'LOWER(vehicle_make_model) LIKE ?'; $params[] = "%{$t}%"; }
                            if (!empty($ands)) {
                                $sql = 'SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND ' . implode(' AND ', $ands) . ' ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1';
                                $pvStmt3 = $pdo->prepare($sql);
                                $pvStmt3->execute($params);
                                $pv = $pvStmt3->fetch();
                            }
                        }

                        if ($pv) {
                            $it['price_part'] = $pv['price'];
                            $it['db_vehicle'] = $pv['vehicle_make_model'];
                        } else {
                            // No existing vehicle price found; create one only if invoice provided a price
                            if (!empty($it['price_part']) && floatval($it['price_part']) > 0) {
                                $ins = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?)');
                                $ins->execute(['part', $it['db_id'], $vehicleMake, floatval($it['price_part']), $_SESSION['user_id']]);
                                $created_items[] = ['type' => 'part_price', 'name' => $name, 'vehicle' => $vehicleMake, 'price' => floatval($it['price_part']), 'item_id' => $it['db_id']];
                            }
                        }
                    }
                }
            }

            // If still not identified and a service price exists, try labors
            if (empty($it['db_id']) && !empty($it['price_svc']) && floatval($it['price_svc']) > 0) {
                if ($vehicleMake !== '') {
                    $vLower = strtolower($vehicleMake);
                    $vLike = "%{$vLower}%";
                    $stmt = $pdo->prepare("SELECT * FROM labors WHERE name = ? AND ((vehicle_make_model IS NOT NULL AND LOWER(vehicle_make_model) LIKE ?) OR vehicle_make_model IS NULL) ORDER BY CASE WHEN LOWER(vehicle_make_model) = ? THEN 0 WHEN LOWER(vehicle_make_model) LIKE ? THEN 1 ELSE 2 END LIMIT 1");
                    $stmt->execute([$name, $vLike, $vLower, $vLike]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM labors WHERE name = ? LIMIT 1");
                    $stmt->execute([$name]);
                }
                $found = $stmt->fetch();
                if ($found) {
                    $it['db_id'] = $found['id'];
                    $it['db_type'] = 'labor';
                    $it['db_vehicle'] = $found['vehicle_make_model'] ?? null;
                    if (empty($it['price_svc']) && !empty($found['default_price'])) $it['price_svc'] = $found['default_price'];

                    // vehicle-specific price logic
                    if ($vehicleMake !== '') {
                        // Smart lookup: try exact match first, then token-based matching (e.g., 'Corolla' matches 'Toyota Corolla')
                        $pv = false;
                        $vLower = strtolower(trim($vehicleMake));

                        $pvStmt = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1');
                        $pvStmt->execute(['labor', $it['db_id'], $vLower]);
                        $pv = $pvStmt->fetch();

                        if (!$pv) {
                            // Try containing full string
                            $pvStmt2 = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                            $pvStmt2->execute(['labor', $it['db_id'], "%{$vLower}%"]);
                            $pv = $pvStmt2->fetch();
                        }

                        if (!$pv) {
                            // Try token AND-matching (all tokens must appear in vehicle_make_model)
                            $tokens = preg_split('/\s+/', $vLower);
                            $ands = [];
                            $params = ['labor', $it['db_id']];
                            foreach ($tokens as $t) { if (trim($t) === '') continue; $ands[] = 'LOWER(vehicle_make_model) LIKE ?'; $params[] = "%{$t}%"; }
                            if (!empty($ands)) {
                                $sql = 'SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND ' . implode(' AND ', $ands) . ' ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1';
                                $pvStmt3 = $pdo->prepare($sql);
                                $pvStmt3->execute($params);
                                $pv = $pvStmt3->fetch();
                            }
                        }

                        if ($pv) {
                            $it['price_svc'] = $pv['price'];
                            $it['db_vehicle'] = $pv['vehicle_make_model'];
                        } else {
                            // No existing vehicle price found; create one only if invoice provided a price
                            if (!empty($it['price_svc']) && floatval($it['price_svc']) > 0) {
                                $ins = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?)');
                                $ins->execute(['labor', $it['db_id'], $vehicleMake, floatval($it['price_svc']), $_SESSION['user_id']]);
                                $created_items[] = ['type' => 'labor_price', 'name' => $name, 'vehicle' => $vehicleMake, 'price' => floatval($it['price_svc']), 'item_id' => $it['db_id']];
                            }
                        }
                    }
                }
            }

            // If still not identified, try to find any match in parts then labors (no price given) but do not create new items
            if (empty($it['db_id'])) {
                if ($vehicleMake !== '') {
                    $stmt = $pdo->prepare("SELECT * FROM parts WHERE name = ? AND (vehicle_make_model = ? OR vehicle_make_model IS NULL) ORDER BY CASE WHEN vehicle_make_model = ? THEN 0 ELSE 1 END LIMIT 1");
                    $stmt->execute([$name, $vehicleMake, $vehicleMake]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM parts WHERE name = ? LIMIT 1");
                    $stmt->execute([$name]);
                }
                $found = $stmt->fetch();
                if ($found) {
                    $it['db_id'] = $found['id'];
                    $it['db_type'] = 'part';
                    if (empty($it['price_part']) && !empty($found['default_price'])) $it['price_part'] = $found['default_price'];
                    if ($vehicleMake !== '') {
                        $pvStmt = $pdo->prepare('SELECT price FROM item_prices WHERE item_type = ? AND item_id = ? AND vehicle_make_model = ? LIMIT 1');
                        $pvStmt->execute(['part', $it['db_id'], $vehicleMake]);
                        $pv = $pvStmt->fetchColumn();
                        if ($pv !== false) { $it['price_part'] = $pv; $it['db_vehicle'] = $vehicleMake; }
                    }
                } else {
                    if ($vehicleMake !== '') {
                        $stmt = $pdo->prepare("SELECT * FROM labors WHERE name = ? AND (vehicle_make_model = ? OR vehicle_make_model IS NULL) ORDER BY CASE WHEN vehicle_make_model = ? THEN 0 ELSE 1 END LIMIT 1");
                        $stmt->execute([$name, $vehicleMake, $vehicleMake]);
                    } else {
                        $stmt = $pdo->prepare("SELECT * FROM labors WHERE name = ? LIMIT 1");
                        $stmt->execute([$name]);
                    }
                    $found = $stmt->fetch();
                    if ($found) {
                        $it['db_id'] = $found['id'];
                        $it['db_type'] = 'labor';
                        if (empty($it['price_svc']) && !empty($found['default_price'])) $it['price_svc'] = $found['default_price'];
                        if ($vehicleMake !== '') {
                            $pvStmt = $pdo->prepare('SELECT price FROM item_prices WHERE item_type = ? AND item_id = ? AND vehicle_make_model = ? LIMIT 1');
                            $pvStmt->execute(['labor', $it['db_id'], $vehicleMake]);
                            $pv = $pvStmt->fetchColumn();
                            if ($pv !== false) { $it['price_svc'] = $pv; $it['db_vehicle'] = $vehicleMake; }
                        }
                    }
                }
            }
        }
    }
    unset($it);

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

    // Validate vehicle_id exists if provided
    if ($vehicle_id !== null) {
        $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? LIMIT 1');
        $stmt->execute([$vehicle_id]);
        if (!$stmt->fetch()) {
            error_log("Vehicle ID $vehicle_id does not exist in database");
            throw new Exception('Invalid vehicle reference. Please try again.');
        }
    }

    $vin = trim($data['vin'] ?? '');

    if ($existing_id) {
        // Update existing invoice (include VIN)
        $stmt = $pdo->prepare("UPDATE invoices SET creation_date = ?, service_manager = ?, service_manager_id = ?, vehicle_id = ?, customer_name = ?, phone = ?, car_mark = ?, plate_number = ?, vin = ?, mileage = ?, items = ?, parts_total = ?, service_total = ?, grand_total = ? WHERE id = ?");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $vehicle_id,
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
        $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, service_manager, service_manager_id, vehicle_id, customer_name, phone, car_mark, plate_number, vin, mileage, items, parts_total, service_total, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $vehicle_id,
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

    // Handle deleted existing images
    if (!empty($_POST['deleted_images'])) {
        $deletedIndices = json_decode($_POST['deleted_images'], true) ?: [];
        if (!empty($deletedIndices)) {
            try {
                // Fetch current images
                $stmt = $pdo->prepare('SELECT images FROM invoices WHERE id = ? LIMIT 1');
                $stmt->execute([$invoice_id]);
                $row = $stmt->fetch();
                $currentImages = $row && !empty($row['images']) ? json_decode($row['images'], true) : [];

                // Remove images at specified indices and delete files
                $indicesToDelete = array_unique(array_map('intval', $deletedIndices));
                sort($indicesToDelete, SORT_DESC); // Delete from highest index first

                foreach ($indicesToDelete as $index) {
                    if (isset($currentImages[$index])) {
                        $imagePath = $currentImages[$index];
                        // Delete file from filesystem
                        $fullPath = __DIR__ . '/' . $imagePath;
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                        // Remove from array
                        unset($currentImages[$index]);
                    }
                }

                // Re-index array
                $currentImages = array_values($currentImages);

                // Update database
                $stmt = $pdo->prepare('UPDATE invoices SET images = ? WHERE id = ?');
                $stmt->execute([json_encode($currentImages, JSON_UNESCAPED_UNICODE), $invoice_id]);

            } catch (Exception $e) {
                error_log('Failed to delete existing images for invoice ' . $invoice_id . ': ' . $e->getMessage());
            }
        }
    }
    if (!empty($_FILES['images'])) {
        $uploaded = $_FILES['images'];
        $stored = [];
        $uploadDir = __DIR__ . '/uploads/invoices/' . $invoice_id;
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        // Normalize structure and validate
        $fileCount = is_array($uploaded['name']) ? count($uploaded['name']) : 0;
        $maxFiles = 20; // Maximum 20 images per invoice
        $maxFileSize = 10 * 1024 * 1024; // 10MB per file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if ($fileCount > $maxFiles) {
            error_log("Too many files uploaded for invoice $invoice_id: $fileCount");
            $fileCount = $maxFiles; // Process only first 20
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($uploaded['name'][$i])) continue;

            // Validate file
            if ($uploaded['error'][$i] !== UPLOAD_ERR_OK) {
                error_log("Upload error for file $i in invoice $invoice_id: " . $uploaded['error'][$i]);
                continue;
            }

            if ($uploaded['size'][$i] > $maxFileSize) {
                error_log("File too large for invoice $invoice_id: " . $uploaded['size'][$i]);
                continue;
            }

            if (!in_array($uploaded['type'][$i], $allowedTypes)) {
                error_log("Invalid file type for invoice $invoice_id: " . $uploaded['type'][$i]);
                continue;
            }

            $tmp = $uploaded['tmp_name'][$i];
            $orig = basename($uploaded['name'][$i]);

            // Sanitize filename and prevent conflicts
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $filename = $base . '_' . time() . '_' . $i . '.' . $ext;

            $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                // Store relative path for web access
                $rel = 'uploads/invoices/' . $invoice_id . '/' . $filename;
                $stored[] = $rel;
            } else {
                error_log("Failed to move uploaded file for invoice $invoice_id: $orig");
            }
        }

        if (!empty($stored)) {
            try {
                // Fetch existing images
                $stmt = $pdo->prepare('SELECT images FROM invoices WHERE id = ? LIMIT 1');
                $stmt->execute([$invoice_id]);
                $row = $stmt->fetch();
                $existingImages = $row && !empty($row['images']) ? json_decode($row['images'], true) : [];

                // Merge new images with existing ones
                $merged = array_values(array_filter(array_merge($existingImages ?: [], $stored)));

                // Limit total images to prevent database bloat
                if (count($merged) > 50) {
                    $merged = array_slice($merged, 0, 50);
                }

                $stmt = $pdo->prepare('UPDATE invoices SET images = ? WHERE id = ?');
                $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), $invoice_id]);
            } catch (PDOException $e) {
                error_log('Failed to save uploaded images for invoice ' . $invoice_id . ': ' . $e->getMessage());
            }
        }
    }

    // Store created items info into session for UI notification
    if (!empty($created_items)) {
        $_SESSION['created_items'] = $created_items;
    }

    // Redirect based on flag
    if (!empty($data['print_after_save'])) {
        header('Location: print_invoice.php?id=' . $invoice_id);
    } elseif ($existing_id) {
        // After update, go back to manager
        header('Location: manager.php?updated=' . $invoice_id);
    } else {
        header('Location: view_invoice.php?id=' . $invoice_id);
    }
    exit;
}
?>