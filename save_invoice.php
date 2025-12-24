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
    };    $created_items = [];
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
                $priceField = $it['db_type'] === 'part' ? 'price_part' : 'price_svc';
                // Smart lookup for vehicle price - prefer most-used historical price first
                $pv = false;
                $vLower = strtolower(trim($vehicleMake));
                // Try historical usage-derived price first (if any)
                if (is_callable($findVehiclePriceUsage)) {
                    $pv = $findVehiclePriceUsage($it['db_type'], $it['db_id'], $vehicleMake);
                    if ($pv) {
                        if (empty($it[$priceField]) || floatval($it[$priceField]) == floatval($pv['price'])) {
                            $it[$priceField] = $pv['price'];
                            $it['db_vehicle'] = $pv['vehicle_make_model'];
                        }
                    }
                }

                // Fallback to configured per-vehicle item_prices if no historical suggestion found
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
                        $ins = $pdo->prepare('INSERT INTO parts (name, description, default_price, vehicle_make_model, created_by) VALUES (?, ?, ?, ?, ?)');
                        $ins->execute([$name, $it['description'] ?? null, floatval($it['price_part']), $vehicleMake !== '' ? trim($vehicleMake) : null, $_SESSION['user_id']]);
                        $newPartId = $pdo->lastInsertId();
                        error_log("save_invoice: attempted to create part '$name' price={$it['price_part']}, newPartId={$newPartId}");
                    } catch (PDOException $e) {
                        error_log("save_invoice: FAILED to create part '$name' price={$it['price_part']}: " . $e->getMessage());
                        $newPartId = null;
                    }

                    if ($newPartId) {
                        $it['db_id'] = $newPartId;
                        $it['db_type'] = 'part';
                        $it['db_vehicle'] = $vehicleMake !== '' ? trim($vehicleMake) : null;
                        $created_items[] = ['type' => 'part', 'name' => $name, 'price' => floatval($it['price_part']), 'item_id' => $newPartId];
                        // if vehicle price is provided, insert into item_prices
                        if ($vehicleMake !== '' && !empty($it['price_part'])) {
                            try {
                                $vehicleCanonical = trim($vehicleMake);
                                $insP = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP');
                                $insP->execute(['part', $newPartId, $vehicleCanonical, floatval($it['price_part']), $_SESSION['user_id']]);
                                error_log("save_invoice: upserted item_price for part id={$newPartId} vehicle={$vehicleCanonical} price={$it['price_part']}");
                                $it['db_vehicle'] = $vehicleCanonical;
                                $created_items[] = ['type' => 'part_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_part']), 'item_id' => $newPartId];
                            } catch (PDOException $e) {
                                error_log("save_invoice: FAILED to create item_price for part id={$newPartId} vehicle={$vehicleCanonical} price={$it['price_part']}: " . $e->getMessage());
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
                    $defaultPrice = floatval($found['default_price'] ?? 0);
                    if (empty($it['price_svc'])) $it['price_svc'] = $defaultPrice;

                    // vehicle-specific price logic
                    if ($vehicleMake !== '') {
                        // Smart lookup: try historical most-used price first, then exact/token matching against item_prices
                        $pv = false;
                        $vLower = strtolower(trim($vehicleMake));

                        if (is_callable($findVehiclePriceUsage)) {
                            $pv = $findVehiclePriceUsage('labor', $it['db_id'], $vehicleMake);
                            if ($pv) {
                                if (empty($it['price_svc']) || floatval($it['price_svc']) == floatval($pv['price'])) {
                                    $it['price_svc'] = $pv['price'];
                                    $it['db_vehicle'] = $pv['vehicle_make_model'];
                                }
                            }
                        }

                        if (!$pv) {
                            // Smart lookup: try exact match first, then token-based matching (e.g., 'Corolla' matches 'Toyota Corolla')
                            $pvStmt = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) = ? LIMIT 1');
                            $pvStmt->execute(['labor', $it['db_id'], $vLower]);
                            $pv = $pvStmt->fetch();
                        }

                        if (!$pv) {
                            // Try containing full string
                            $pvStmt2 = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                            $pvStmt2->execute(['labor', $it['db_id'], "%{$vLower}%"]);
                            $pv = $pvStmt2->fetch();
                        }

                        if (!$pv) {
                            // Token OR matching: check if any token from typed value matches stored vehicle (e.g., 'Audi Q5' -> token 'q5' matches stored 'Q5')
                            $tokens = preg_split('/\s+/', $vLower);
                            foreach ($tokens as $t) {
                                $t = trim($t); if ($t === '') continue;
                                $pvStmtTok = $pdo->prepare('SELECT id, price, vehicle_make_model FROM item_prices WHERE item_type = ? AND item_id = ? AND LOWER(vehicle_make_model) LIKE ? ORDER BY LENGTH(vehicle_make_model) DESC LIMIT 1');
                                $pvStmtTok->execute(['labor', $it['db_id'], "%{$t}%"]);
                                $pv = $pvStmtTok->fetch();
                                if ($pv) break;
                            }

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
                            if (empty($it['price_svc']) || floatval($it['price_svc']) == floatval($pv['price'])) {
                                $it['price_svc'] = $pv['price'];
                                $it['db_vehicle'] = $pv['vehicle_make_model'];
                            } else {
                                // Update existing vehicle price
                                $pdo->prepare("UPDATE item_prices SET price = ?, created_by = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([floatval($it['price_svc']), $_SESSION['user_id'], $pv['id']]);
                                error_log("save_invoice: updated item_price id={$pv['id']} to price=" . floatval($it['price_svc']) . " for labor id={$it['db_id']}");
                                $it['db_vehicle'] = $pv['vehicle_make_model'];
                            }
                        } else {
                            if (!empty($it['price_svc']) && floatval($it['price_svc']) != $defaultPrice) {
                                // Create or update vehicle price
                                $vehicleCanonical = trim($vehicleMake);
                                $ins = $pdo->prepare("INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP");
                                $ins->execute(['labor', $it['db_id'], $vehicleCanonical, floatval($it['price_svc']), $_SESSION['user_id']]);
                                $it['db_vehicle'] = $vehicleCanonical;
                                $created_items[] = ['type' => 'labor_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_svc']), 'item_id' => $it['db_id']];
                                error_log("save_invoice: upserted item_price for labor id={$it['db_id']} vehicle={$vehicleCanonical} price={$it['price_svc']}");
                            }
                        }
                    }
                }
            }

            // If still not identified after search and we have a labor price, first try attaching to an existing labor by name, otherwise create a new labor entry
            if (empty($it['db_id']) && !empty($it['price_svc']) && floatval($it['price_svc']) > 0) {
                // Try to find existing labor by exact name
                $stmtExistingL = $pdo->prepare("SELECT * FROM labors WHERE name = ? LIMIT 1");
                $stmtExistingL->execute([$name]);
                $existingLabor = $stmtExistingL->fetch();

                if ($existingLabor) {
                    // Attach to existing labor and upsert vehicle-specific price if provided
                    $it['db_id'] = $existingLabor['id'];
                    $it['db_type'] = 'labor';
                    $it['db_vehicle'] = $existingLabor['vehicle_make_model'] ?? null;
                    $defaultPrice = floatval($existingLabor['default_price'] ?? 0);
                    if (empty($it['price_svc'])) $it['price_svc'] = $defaultPrice;

                    if ($vehicleMake !== '' && !empty($it['price_svc']) && floatval($it['price_svc']) != $defaultPrice) {
                        try {
                            $vehicleCanonical = trim($vehicleMake);
                            $insP = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP');
                            $insP->execute(['labor', $it['db_id'], $vehicleCanonical, floatval($it['price_svc']), $_SESSION['user_id']]);
                            $it['db_vehicle'] = $vehicleCanonical;
                            $created_items[] = ['type' => 'labor_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_svc']), 'item_id' => $it['db_id']];
                            error_log("save_invoice: attached price to existing labor id={$it['db_id']} vehicle={$vehicleCanonical} price={$it['price_svc']}");
                        } catch (PDOException $e) {
                            error_log("save_invoice: FAILED to upsert item_price for existing labor id={$it['db_id']} vehicle={$vehicleCanonical} price={$it['price_svc']}: " . $e->getMessage());
                        }
                    } else {
                        error_log("save_invoice: attached to existing labor id={$it['db_id']} name={$name} without creating new");
                    }
                } else {
                    try {
                        $ins = $pdo->prepare('INSERT INTO labors (name, description, default_price, vehicle_make_model, created_by) VALUES (?, ?, ?, ?, ?)');
                        $ins->execute([$name, $it['description'] ?? null, floatval($it['price_svc']), $vehicleMake !== '' ? trim($vehicleMake) : null, $_SESSION['user_id']]);
                        $newLaborId = $pdo->lastInsertId();
                        error_log("save_invoice: attempted to create labor '$name' price={$it['price_svc']}, newLaborId={$newLaborId}");
                    } catch (PDOException $e) {
                        error_log("save_invoice: FAILED to create labor '$name' price={$it['price_svc']}: " . $e->getMessage());
                        $newLaborId = null;
                    }

                    if ($newLaborId) {
                        $it['db_id'] = $newLaborId;
                        $it['db_type'] = 'labor';
                        $it['db_vehicle'] = $vehicleMake !== '' ? trim($vehicleMake) : null;
                        $created_items[] = ['type' => 'labor', 'name' => $name, 'price' => floatval($it['price_svc']), 'item_id' => $newLaborId];
                        // create vehicle-specific price record if applicable
                        if ($vehicleMake !== '' && !empty($it['price_svc'])) {
                            try {
                                $vehicleCanonical = trim($vehicleMake);
                                $insP = $pdo->prepare('INSERT INTO item_prices (item_type, item_id, vehicle_make_model, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP');
                                $insP->execute(['labor', $newLaborId, $vehicleCanonical, floatval($it['price_svc']), $_SESSION['user_id']]);
                                error_log("save_invoice: upserted item_price for new labor id={$newLaborId} vehicle={$vehicleCanonical} price={$it['price_svc']}");
                                $it['db_vehicle'] = $vehicleCanonical;
                                $created_items[] = ['type' => 'labor_price', 'name' => $name, 'vehicle' => $vehicleCanonical, 'price' => floatval($it['price_svc']), 'item_id' => $newLaborId];
                            } catch (PDOException $e) {
                                error_log("save_invoice: FAILED to create item_price for labor id={$newLaborId} vehicle={$vehicleCanonical} price={$it['price_svc']}: " . $e->getMessage());
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

    // Resolve technician display name when an id is provided
    $technicianName = $data['technician'] ?? '';
    if (!empty($data['technician_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM technicians WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$data['technician_id']]);
        $tm = $stmt->fetch();
        if ($tm) $technicianName = $tm['name'];
    }

    // Ensure service manager defaults to current logged-in user if empty
    if (empty($serviceManagerName) && !empty($_SESSION['username'])) {
        $serviceManagerName = $_SESSION['username'];
    }

    // Calculate totals from items if not provided or invalid
    $partsTotal = 0.00;
    $serviceTotal = 0.00;
    $grandTotal = 0.00;

    foreach ($items as $idx => $item) {
        $qty = (float)($item['qty'] ?? 0);
        $pricePart = (float)($item['price_part'] ?? 0);
        $discountPart = isset($item['discount_part']) ? floatval($item['discount_part']) : 0.0;
        $priceSvc = (float)($item['price_svc'] ?? 0);
        $discountSvc = isset($item['discount_svc']) ? floatval($item['discount_svc']) : 0.0;

        $linePart = $qty * $pricePart * max(0, (1 - $discountPart / 100.0));
        $lineSvc = $qty * $priceSvc * max(0, (1 - $discountSvc / 100.0));

        // store computed line_total for labor for future reference
        if (!empty($item['db_type']) && $item['db_type'] === 'labor') {
            $items[$idx]['line_total'] = $lineSvc;
        } elseif (isset($item['price_svc']) && $item['price_svc'] > 0) {
            // heuristically treat lines with price_svc as labor
            $items[$idx]['line_total'] = $lineSvc;
        }

        $partsTotal += $linePart;
        $serviceTotal += $lineSvc;
    }
    $grandTotal = $partsTotal + $serviceTotal;

    // Calculate oil totals
    $oilsTotal = 0.00;
    foreach ($oils as $oil) {
        // Get oil price from database
        $stmt = $pdo->prepare("SELECT price FROM oil_prices WHERE brand_id = ? AND viscosity_id = ? AND package_type = ? LIMIT 1");
        $stmt->execute([$oil['brand_id'], $oil['viscosity_id'], $oil['package_type']]);
        $priceData = $stmt->fetch();
        
        if ($priceData) {
            $unitPrice = (float)$priceData['price'];
            $qty = (int)($oil['qty'] ?? 1);
            $discount = isset($oil['discount']) ? floatval($oil['discount']) : 0.0;
            $lineTotal = $qty * $unitPrice * max(0, (1 - $discount / 100.0));
            $oilsTotal += $lineTotal;
        }
    }

    // DEBUG: what we will store in DB
    error_log("save_invoice: oilsTotal={$oilsTotal}, oils_to_store=" . json_encode($oils) . "\n");

    // Read posted global discounts (if any)
    $parts_discount_percent = isset($data['parts_discount_percent']) ? floatval($data['parts_discount_percent']) : 0.0;
    $service_discount_percent = isset($data['service_discount_percent']) ? floatval($data['service_discount_percent']) : 0.0;

    // Apply global discounts to calculated totals
    $calcPartsAfterGlobal = $partsTotal * max(0, (1 - $parts_discount_percent / 100.0));
    $calcServiceAfterGlobal = $serviceTotal * max(0, (1 - $service_discount_percent / 100.0));
    $calcOilsAfterGlobal = $oilsTotal; // Oils don't have global discounts applied
    $calcGrandAfterGlobal = $calcPartsAfterGlobal + $calcServiceAfterGlobal + $calcOilsAfterGlobal;

    // Use calculated values if POST values are empty or invalid
    $providedPartsTotal = isset($data['parts_total']) && is_numeric($data['parts_total']) ? (float)$data['parts_total'] : null;
    $providedServiceTotal = isset($data['service_total']) && is_numeric($data['service_total']) ? (float)$data['service_total'] : null;
    $providedGrandTotal = isset($data['grand_total']) && is_numeric($data['grand_total']) ? (float)$data['grand_total'] : null;

    // Use provided values if they match calculations (within small tolerance), otherwise use calculated after global discount
    $finalPartsTotal = ($providedPartsTotal !== null && abs($providedPartsTotal - $calcPartsAfterGlobal) < 0.01) ? $providedPartsTotal : $calcPartsAfterGlobal;
    $finalServiceTotal = ($providedServiceTotal !== null && abs($providedServiceTotal - $calcServiceAfterGlobal) < 0.01) ? $providedServiceTotal : $calcServiceAfterGlobal;
    $finalGrandTotal = ($providedGrandTotal !== null && abs($providedGrandTotal - $calcGrandAfterGlobal) < 0.01) ? $providedGrandTotal : $calcGrandAfterGlobal;

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
        // Update existing invoice (include VIN, technician)
        $stmt = $pdo->prepare("UPDATE invoices SET creation_date = ?, service_manager = ?, service_manager_id = ?, technician = ?, technician_id = ?, vehicle_id = ?, customer_name = ?, phone = ?, car_mark = ?, plate_number = ?, vin = ?, mileage = ?, items = ?, oils = ?, parts_total = ?, service_total = ?, parts_discount_percent = ?, service_discount_percent = ?, grand_total = ? WHERE id = ?");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $technicianName,
            !empty($data['technician_id']) ? (int)$data['technician_id'] : NULL,
            $vehicle_id,
            $data['customer_name'],
            $data['phone_number'],
            $data['car_mark'],
            $data['plate_number'],
            $vin,
            $data['mileage'],
            json_encode($items),
            json_encode($oils),
            $finalPartsTotal,
            $finalServiceTotal,
            $parts_discount_percent,
            $service_discount_percent,
            $finalGrandTotal,
            $existing_id
        ]);
        // After update, fetch saved oils for debugging
        $stmt = $pdo->prepare('SELECT oils FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$existing_id]);
        $saved = $stmt->fetch();
        error_log("save_invoice: updated invoice {$existing_id}, saved_oils=" . ($saved ? $saved['oils'] : 'NULL') . "\n");
        $invoice_id = $existing_id;
    } else {
        // Insert new invoice (include VIN, technician)
        $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, service_manager, service_manager_id, technician, technician_id, vehicle_id, customer_name, phone, car_mark, plate_number, vin, mileage, items, oils, parts_total, service_total, parts_discount_percent, service_discount_percent, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['creation_date'],
            $serviceManagerName,
            !empty($data['service_manager_id']) ? (int)$data['service_manager_id'] : NULL,
            $technicianName,
            !empty($data['technician_id']) ? (int)$data['technician_id'] : NULL,
            $vehicle_id,
            $data['customer_name'],
            $data['phone_number'],
            $data['car_mark'],
            $data['plate_number'],
            $vin,
            $data['mileage'],
            json_encode($items),
            json_encode($oils),
            $finalPartsTotal,
            $finalServiceTotal,
            $parts_discount_percent,
            $service_discount_percent,
            $finalGrandTotal,
            $_SESSION['user_id']
        ]);
        // After insert, fetch and log saved oils for debugging
        $invoice_id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT oils FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$invoice_id]);
        $saved = $stmt->fetch();
        error_log("save_invoice: inserted invoice {$invoice_id}, saved_oils=" . ($saved ? $saved['oils'] : 'NULL') . "\n");
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
    // Record historical usage of prices for items (increment most-used counts)
    try {
        foreach ($items as $it) {
            if (empty($it['db_id']) || empty($it['db_type'])) continue;
            $priceField = $it['db_type'] === 'part' ? 'price_part' : 'price_svc';
            $priceVal = isset($it[$priceField]) ? floatval($it[$priceField]) : 0.0;
            if ($priceVal <= 0) continue;

            // Prefer attached db_vehicle if present, otherwise use invoice vehicleMake (can be blank/null)
            $vehicleCanonical = !empty($it['db_vehicle']) ? trim($it['db_vehicle']) : ($vehicleMake !== '' ? trim($vehicleMake) : null);

            $up = $pdo->prepare("INSERT INTO item_price_usage (item_type, item_id, vehicle_make_model, price, usage_count, last_used_at) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE usage_count = usage_count + 1, last_used_at = CURRENT_TIMESTAMP");
            $up->execute([$it['db_type'], $it['db_id'], $vehicleCanonical, $priceVal]);
        }
    } catch (Exception $e) {
        error_log('save_invoice: failed to update item_price_usage: ' . $e->getMessage());
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
        error_log("save_invoice: created_items: " . json_encode($created_items));
        $_SESSION['created_items'] = $created_items;
    }

    // Final processing complete — enqueue created items into session when present
    // (production: minimal logging)

    // Redirect based on flag
    if (!empty($data['print_after_save'])) {
        if (!empty($invoice_id) && is_numeric($invoice_id)) {
            header('Location: print_invoice.php?id=' . $invoice_id);
        } else {
            // Log unexpected missing invoice id and redirect to manager with error
            error_log("save_invoice: print_after_save requested but invoice_id missing or invalid. POST keys: " . json_encode(array_keys($_POST)) . " parsed invoice_id=" . var_export($invoice_id, true));
            header('Location: manager.php?error=missing_invoice_id');
        }
    } elseif ($existing_id) {
        // After update, go back to manager
        header('Location: manager.php?updated=' . $invoice_id);
    } else {
        header('Location: view_invoice.php?id=' . $invoice_id);
    }
    exit;
?>