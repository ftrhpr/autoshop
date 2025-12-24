<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST; // production: reduce verbose debug logging
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
                'discount_part' => isset($data["item_discount_part_$i"]) ? floatval($data["item_discount_part_$i"]) : 0.0,
                'price_svc' => $data["item_price_svc_$i"],
                'discount_svc' => isset($data["item_discount_svc_$i"]) ? floatval($data["item_discount_svc_$i"]) : 0.0,
                'tech' => $data["item_tech_$i"],
                'tech_id' => isset($data["item_tech_id_$i"]) ? (int)$data["item_tech_id_$i"] : null,
                // optional matched DB info from autocomplete
                'db_id' => isset($data["item_db_id_$i"]) ? (int)$data["item_db_id_$i"] : null,
                'db_type' => isset($data["item_db_type_$i"]) ? $data["item_db_type_$i"] : null,
                'db_vehicle' => isset($data["item_db_vehicle_$i"]) ? $data["item_db_vehicle_$i"] : null,
                'db_price_source' => isset($data["item_db_price_source_$i"]) ? $data["item_db_price_source_$i"] : null,
            ];
        }
    }

    // Process oils: support JSON payload ('oils_json') for robustness, fall back to legacy oil_brand_0 fields
    $oils = [];

    if (!empty($data['oils_json'])) {
        // Prefer JSON payload when provided
        $decoded = json_decode($data['oils_json'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $idx => $o) {
                $brand_id = isset($o['brand_id']) ? (int)$o['brand_id'] : 0;
                $viscosity_id = isset($o['viscosity_id']) ? (int)$o['viscosity_id'] : 0;
                $package_type = isset($o['package_type']) ? trim($o['package_type']) : '';
                $qty = isset($o['qty']) && is_numeric($o['qty']) ? max(1, (int)$o['qty']) : 1;
                $discount = isset($o['discount']) && is_numeric($o['discount']) ? floatval($o['discount']) : 0.0;
                if ($brand_id > 0 && $viscosity_id > 0 && $package_type !== '') {
                    $oils[] = ['brand_id' => $brand_id, 'viscosity_id' => $viscosity_id, 'package_type' => $package_type, 'qty' => $qty, 'discount' => $discount];
                } else {
                    error_log("save_invoice: skipping incomplete oil object at JSON index {$idx}: " . json_encode($o) . "\n");
                }
            }
            error_log("save_invoice: parsed oils from oils_json: " . json_encode($oils) . "\n");
        } else {
            error_log('save_invoice: failed to decode oils_json: ' . json_last_error_msg());
        }
    } else {
        // Legacy parsing for compatibility
        // Collect raw oil_* POST entries (legacy support) - no verbose logging in production
        $oil_post_entries = [];
        foreach ($data as $k => $v) {
            if (strpos($k, 'oil_') === 0) $oil_post_entries[$k] = $v;
        }

        for ($i = 0; isset($data["oil_brand_$i"]); $i++) {
            $brand_id = trim($data["oil_brand_$i"] ?? '');
            $viscosity_id = trim($data["oil_viscosity_$i"] ?? '');
            $package_type = trim($data["oil_package_$i"] ?? '');

            // Parse qty and discount defensively
            $qty_raw = isset($data["oil_qty_$i"]) ? $data["oil_qty_$i"] : '1';
            $qty = is_numeric($qty_raw) ? max(1, (int)$qty_raw) : 1;
            $discount_raw = isset($data["oil_discount_$i"]) ? $data["oil_discount_$i"] : '0';
            $discount = is_numeric($discount_raw) ? floatval($discount_raw) : 0.0;

            if ($brand_id !== '' && $viscosity_id !== '' && $package_type !== '') {
                $oils[] = [
                    'brand_id' => (int)$brand_id,
                    'viscosity_id' => (int)$viscosity_id,
                    'package_type' => $package_type,
                    'qty' => $qty,
                    'discount' => $discount,
                ];
            } else {
                error_log("save_invoice: skipping incomplete oil row at index {$i}: brand='{$brand_id}' viscosity='{$viscosity_id}' package='{$package_type}' qty_raw='{$qty_raw}' discount_raw='{$discount_raw}'\n");
            }
        }

        // Legacy parsed oils (no verbose logging in production)
    }

    // Normalize / deduplicate oils by brand + viscosity + package + discount (sum qtys)
    $normalized = [];
    $raw_qty_map = [];
    foreach ($oils as $o) {
        // Ensure qty is integer and at least 1 (defensive)
        $o['qty'] = isset($o['qty']) ? max(1, (int)$o['qty']) : 1;
        $key = $o['brand_id'] . '_' . $o['viscosity_id'] . '_' . $o['package_type'] . '_' . $o['discount'];
        if (!isset($raw_qty_map[$key])) $raw_qty_map[$key] = [];
        $raw_qty_map[$key][] = $o['qty'];

        if (isset($normalized[$key])) {
            $normalized[$key]['qty'] += $o['qty'];
        } else {
            $normalized[$key] = $o;
        }
    }
    $oils = array_values($normalized);

    // Additional sanity checks: if any normalized qty doesn't match sum of raw quantities, log warning
    foreach ($oils as $k => $no) {
        $key = $no['brand_id'] . '_' . $no['viscosity_id'] . '_' . $no['package_type'] . '_' . $no['discount'];
        $sum = array_sum($raw_qty_map[$key] ?? []);
        if ($sum !== (int)$no['qty']) {
            error_log("save_invoice: WARNING: normalized qty mismatch for key={$key}: expected_sum={$sum} normalized_saved={$no['qty']} raw_list=" . json_encode($raw_qty_map[$key] ?? []) . "\n");
        }
    }

    // normalized oils computed (production: not logged)

    // Handle vehicle - find or create for existing customer
    // DEBUG: log incoming items payload to help diagnose missing-created-items issue
    error_log("save_invoice: raw POST keys: " . json_encode(array_keys($_POST)) . "\n");
    error_log("save_invoice: parsed items: " . json_encode($items) . "\n");

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
    // Ensure item_price_usage table exists to track historical price usage (used for suggesting most frequently used price)
    $pdo->exec("CREATE TABLE IF NOT EXISTS item_price_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(10) NOT NULL,
        item_id INT NOT NULL,
        vehicle_make_model VARCHAR(255) DEFAULT NULL,
        price DECIMAL(12,2) NOT NULL,
        usage_count INT NOT NULL DEFAULT 0,
        last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY u_item_vehicle_price (item_type, item_id, vehicle_make_model, price)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Helper: find most-used price for an item + vehicle (falls back to null if not found)
    $findVehiclePriceUsage = function($itemType, $itemId, $vehicleStr) use ($pdo) {
        $vLower = strtolower(trim($vehicleStr));
        if ($vLower === '') return false;

        // Exact match first
        $up = $pdo->prepare("SELECT price, vehicle_make_model FROM item_price_usage WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? ORDER BY usage_count DESC, last_used_at DESC LIMIT 1");
        $up->execute([$itemType, $itemId, $vLower]);
        $r = $up->fetch(PDO::FETCH_ASSOC);
        if ($r) return $r;

        // Containing full string
        $up2 = $pdo->prepare("SELECT price, vehicle_make_model FROM item_price_usage WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY usage_count DESC, last_used_at DESC LIMIT 1");
        $up2->execute([$itemType, $itemId, "%{$vLower}%"]);
        $r = $up2->fetch(PDO::FETCH_ASSOC);
        if ($r) return $r;

        // Token OR matching
        $tokens = preg_split('/\s+/', $vLower);
        foreach ($tokens as $t) {
            $t = trim($t); if ($t === '') continue;
            $upTok = $pdo->prepare("SELECT price, vehicle_make_model FROM item_price_usage WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY usage_count DESC, last_used_at DESC LIMIT 1");
            $upTok->execute([$itemType, $itemId, "%{$t}%"]);
            $r = $upTok->fetch(PDO::FETCH_ASSOC);
            if ($r) return $r;
        }

        // Token AND matching
        $ands = [];
        $params = [$itemType, $itemId];
        foreach ($tokens as $t) { if (trim($t) === '') continue; $ands[] = 'LOWER(vehicle_make_model) LIKE ?'; $params[] = "%{$t}%"; }
        if (!empty($ands)) {
            $sql = 'SELECT price, vehicle_make_model FROM item_price_usage WHERE item_type = ? AND item_id = ? AND ' . implode(' AND ', $ands) . ' ORDER BY usage_count DESC, last_used_at DESC LIMIT 1';
            $s2 = $pdo->prepare($sql);
            $s2->execute($params);
            $r = $s2->fetch(PDO::FETCH_ASSOC);
            if ($r) return $r;
        }

        return false;
    };

    $created_items = [];
    foreach ($items as $idx => &$it) {
        $name = trim($it['name'] ?? '');
        if ($name === '') continue;

        // If autocomplete already provided db info, ensure the referenced record exists
        if (!empty($it['db_id']) && !empty($it['db_type'])) {
            // Verify existence and fetch vehicle_make_model
            if ($it['db_type'] === 'part') {
                $v = $pdo->prepare('SELECT id, vehicle_make_model, default_price FROM parts WHERE id = ? LIMIT 1'); $v->execute([$it['db_id']]);
                $vv = $v->fetch();
                if (!$vv) { $it['db_id'] = null; $it['db_type'] = null; } else { $it['db_vehicle'] = $vv['vehicle_make_model'] ?? null; $defaultPrice = floatval($vv['default_price'] ?? 0); }
            } else {
                $v = $pdo->prepare('SELECT id, vehicle_make_model, default_price FROM labors WHERE id = ? LIMIT 1'); $v->execute([$it['db_id']]);
                $vv = $v->fetch();
                if (!$vv) { $it['db_id'] = null; $it['db_type'] = null; } else { $it['db_vehicle'] = $vv['vehicle_make_model'] ?? null; $defaultPrice = floatval($vv['default_price'] ?? 0); }
            }
        }

        // If db_id is set, do price lookup and management
        if (!empty($it['db_id']) && !empty($it['db_type'])) {
            if (empty($it['price_part']) && $it['db_type'] === 'part') $it['price_part'] = $defaultPrice;
            if (empty($it['price_svc']) && $it['db_type'] === 'labor') $it['price_svc'] = $defaultPrice;

            if ($vehicleMake !== '') {
                $pv = false;
                $vLower = strtolower(trim($vehicleMake));

                if (is_callable($findVehiclePriceUsage)) {
                    $pv = $findVehiclePriceUsage($it['db_type'], $it['db_id'], $vehicleMake);
                    if ($pv) {
                        $priceField = $it['db_type'] === 'part' ? 'price_part' : 'price_svc';
                        if (empty($it[$priceField]) || floatval($it[$priceField]) == floatval($pv['price'])) {
                            $it[$priceField] = $pv['price'];
                            $it['db_vehicle'] = $pv['vehicle_make_model'];
                        }
                    }
                }

                if (!$pv) {
                    // exact match
                    $pvStmt = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1');
                    $pvStmt->execute([$it['db_type'], $it['db_id'], $vLower]);
                    $pv = $pvStmt->fetch();

                    // containing full string
                    if (!$pv) {
                        $pvStmt2 = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                        $pvStmt2->execute([$it['db_type'], $it['db_id'], "%{$vLower}%"]);
                        $pv = $pvStmt2->fetch();
                    }

                    // token OR matching
                    if (!$pv) {
                        $tokens = preg_split('/\s+/', $vLower);
                        foreach ($tokens as $t) {
                            $t = trim($t); if ($t === '') continue;
                            $pvStmtTok = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                            $pvStmtTok->execute([$it['db_type'], $it['db_id'], "%{$t}%"]);
                            $pv = $pvStmtTok->fetch();
                            if ($pv) break;
                        }
                    }

                    // token AND matching
                    if (!$pv) {
                        $tokens = preg_split('/\s+/', $vLower);
                        $ands = [];
                        $params = [$it['db_type'], $it['db_id']];
                        foreach ($tokens as $t) { if (trim($t) === '') continue; $ands[] = 'LOWER(vehicle_make_model) LIKE ?'; $params[] = "%{$t}%"; }
                        if (!empty($ands)) {
                            $sql = 'SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND ' . implode(' AND ', $ands) . ' ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1';
                            $pvStmt3 = $pdo->prepare($sql);
                            $pvStmt3->execute($params);
                            $pv = $pvStmt3->fetch();
                        }
                    }
                }

                $priceField = $it['db_type'] === 'part' ? 'price_part' : 'price_svc';

                if ($pv) {
                    if (empty($it[$priceField]) || floatval($it[$priceField]) == floatval($pv['price'])) {
                        $it[$priceField] = $pv['price'];
                        $it['db_vehicle'] = $pv['vehicle_make_model'];
                    } else {
                        // Update existing vehicle price
                        $pdo->prepare("UPDATE item_prices SET price = ?, created_by = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([floatval($it[$priceField]), $_SESSION['user_id'], $pv['id']]);
                        error_log("save_invoice: updated item_price id={$pv['id']} to price=" . floatval($it[$priceField]) . " for item_id={$it['db_id']}");
                        $it['db_vehicle'] = $pv['vehicle_make_model'];
                    }
                } else {
                    if (!empty($it[$priceField]) && floatval($it[$priceField]) != $defaultPrice) {
                        // Create new vehicle price
                        $vehicleCanonical = trim($vehicleMake);
                        $ins = $pdo->prepare("INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP");
                        $ins->execute([$it['db_type'], $it['db_id'], $vehicleCanonical, floatval($it[$priceField]), $_SESSION['user_id']]);
                        $it['db_vehicle'] = $vehicleCanonical;
                        $created_items[] = ['type' => $it['db_type'] . '_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it[$priceField]), 'item_id' => $it['db_id']];
                        error_log("save_invoice: upserted item_price for {$it['db_type']} id={$it['db_id']} vehicle={$vehicleCanonical} price={$it[$priceField]}");
                    }
                }
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
                    $defaultPrice = floatval($found['default_price'] ?? 0);
                    if (empty($it['price_part'])) $it['price_part'] = $defaultPrice;

                    // If vehicle provided, check item_prices for existing vehicle price and use it; otherwise, if invoice contains price, create price entry
                    if ($vehicleMake !== '') {
                        // Smart lookup: try historical most-used price first, then exact/token matching against item_prices
                        $pv = false;
                        $vLower = strtolower(trim($vehicleMake));

                        if (is_callable($findVehiclePriceUsage)) {
                            $pv = $findVehiclePriceUsage('part', $it['db_id'], $vehicleMake);
                            if ($pv) {
                                if (empty($it['price_part']) || floatval($it['price_part']) == floatval($pv['price'])) {
                                    $it['price_part'] = $pv['price'];
                                    $it['db_vehicle'] = $pv['vehicle_make_model'];
                                }
                            }
                        }

                        if (!$pv) {
                            $pvStmt = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1');
                            $pvStmt->execute(['part', $it['db_id'], $vLower]);
                            $pv = $pvStmt->fetch();
                        }

                        if (!$pv) {
                            // Try containing full string
                            $pvStmt2 = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                            $pvStmt2->execute(['part', $it['db_id'], "%{$vLower}%"]);
                            $pv = $pvStmt2->fetch();
                        }

                        if (!$pv) {
                            // Token OR matching: check if any token from typed value matches stored vehicle (e.g., 'Audi Q5' -> token 'q5' matches stored 'Q5')
                            $tokens = preg_split('/\s+/', $vLower);
                            foreach ($tokens as $t) {
                                $t = trim($t); if ($t === '') continue;
                                $pvStmtTok = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                                $pvStmtTok->execute(['part', $it['db_id'], "%{$t}%"]);
                                $pv = $pvStmtTok->fetch();
                                if ($pv) break;
                            }
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
                                if ($pv) error_log("save_invoice: matched part vehicle price (AND) for item_id={$it['db_id']} vehicle={$pv['vehicle_make_model']} price={$pv['price']}");
                            }
                        }

                        if ($pv) {
                            if (empty($it['price_part']) || floatval($it['price_part']) == floatval($pv['price'])) {
                                $it['price_part'] = $pv['price'];
                                $it['db_vehicle'] = $pv['vehicle_make_model'];
                            } else {
                                // Update existing vehicle price
                                $pdo->prepare("UPDATE item_prices SET price = ?, created_by = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([floatval($it['price_part']), $_SESSION['user_id'], $pv['id']]);
                                error_log("save_invoice: updated item_price id={$pv['id']} to price=" . floatval($it['price_part']) . " for part id={$it['db_id']}");
                                $it['db_vehicle'] = $pv['vehicle_make_model'];
                            }
                        } else {
                            if (!empty($it['price_part']) && floatval($it['price_part']) != $defaultPrice) {
                                // Create or update vehicle price
                                $vehicleCanonical = trim($vehicleMake);
                                $ins = $pdo->prepare("INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP");
                                $ins->execute(['part', $it['db_id'], $vehicleCanonical, floatval($it['price_part']), $_SESSION['user_id']]);
                                $it['db_vehicle'] = $vehicleCanonical;
                                $created_items[] = ['type' => 'part_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_part']), 'item_id' => $it['db_id']];
                                error_log("save_invoice: upserted item_price for part id={$it['db_id']} vehicle={$vehicleCanonical} price={$it['price_part']}");
                            }
                        }
                    }
                }

                // If still not identified but part price was provided and nothing found, prefer attaching to existing part by name (avoid duplicates), otherwise create new part
                if (empty($it['db_id']) && !empty($it['price_part']) && floatval($it['price_part']) > 0) {
                    // Try to find an existing part with exact name regardless of vehicle
                    $stmtExisting = $pdo->prepare("SELECT * FROM parts WHERE name = ? LIMIT 1");
                    $stmtExisting->execute([$name]);
                    $existingPart = $stmtExisting->fetch();

                    if ($existingPart) {
                        // Attach to existing part and upsert vehicle-specific price if provided
                        $it['db_id'] = $existingPart['id'];
                        $it['db_type'] = 'part';
                        $it['db_vehicle'] = $existingPart['vehicle_make_model'] ?? null;
                        $defaultPrice = floatval($existingPart['default_price'] ?? 0);
                        if (empty($it['price_part'])) $it['price_part'] = $defaultPrice;

                        if ($vehicleMake !== '' && !empty($it['price_part']) && floatval($it['price_part']) != $defaultPrice) {
                            $vehicleCanonical = trim($vehicleMake);
                            $insP = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP');
                            $insP->execute(['part', $it['db_id'], $vehicleCanonical, floatval($it['price_part']), $_SESSION['user_id']]);
                            $it['db_vehicle'] = $vehicleCanonical;
                            $created_items[] = ['type' => 'part_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_part']), 'item_id' => $it['db_id']];
                            error_log("save_invoice: attached price to existing part id={$it['db_id']} vehicle={$vehicleCanonical} price={$it['price_part']}");
                        } else {
                            error_log("save_invoice: attached to existing part id={$it['db_id']} name={$name} without creating new");
                        }
                    } else {
                        try {
                            $ins = $pdo->prepare('INSERT INTO parts (name, description, default_price, vehicle_make_model, created_by) VALUES (?, ?, ?, ?