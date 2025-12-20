<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !currentUserCan('manage_customers')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers.php');
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['import_summary'] = ['error' => 'No file uploaded or upload error.'];
    header('Location: customers.php');
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$originalName = $_FILES['csv_file']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    $_SESSION['import_summary'] = ['error' => 'Invalid file type. Please upload a CSV file.'];
    header('Location: customers.php');
    exit;
}

$handle = fopen($file, 'r');
if (!$handle) {
    $_SESSION['import_summary'] = ['error' => 'Unable to read uploaded file.'];
    header('Location: customers.php');
    exit;
}

$header = fgetcsv($handle);
if (!$header) {
    $_SESSION['import_summary'] = ['error' => 'CSV appears empty or malformed.'];
    fclose($handle);
    header('Location: customers.php');
    exit;
}

// Normalize header
$columns = array_map(function($c){ return strtolower(trim($c)); }, $header);
$required = ['full_name', 'phone'];
$missing = array_diff($required, $columns);
if (!empty($missing)) {
    $_SESSION['import_summary'] = ['error' => 'CSV must include columns: ' . implode(', ', $missing)];
    fclose($handle);
    header('Location: customers.php');
    exit;
}

$maxRows = 2000; // safety limit
$inserted = 0;
$updated = 0;
$failed = 0;
$failures = [];
$rowNo = 1;

$pdo->beginTransaction();
try {
    $select = $pdo->prepare('SELECT id FROM customers WHERE plate_number = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO customers (full_name, phone, email, plate_number, car_mark, notes, created_by, last_service_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $update = $pdo->prepare('UPDATE customers SET full_name = ?, phone = ?, email = ?, car_mark = ?, notes = ?, last_service_at = ? WHERE id = ?');

    while (($row = fgetcsv($handle)) !== false) {
        $rowNo++;
        if ($rowNo > $maxRows) {
            $failures[] = "Row limit reached at {$maxRows}";
            break;
        }

        $data = array_combine($columns, array_pad($row, count($columns), null));
        if ($data === false) {
            $failed++;
            $failures[] = "Row {$rowNo}: column mismatch";
            continue;
        }

        // Ensure UTF-8 encoding for Georgian support
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true) ?: 'UTF-8');
            }
        }

        $plate = strtoupper(trim($data['plate_number'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        if ($phone === '') {
            $failed++;
            $failures[] = "Row {$rowNo}: empty phone number";
            continue;
        }

        $full = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $car = trim($data['car_mark'] ?? '');
        $notes = trim($data['notes'] ?? '');
        $lastService = trim($data['last_service_at'] ?? null);
        $lastService = $lastService ? date('Y-m-d H:i:s', strtotime($lastService)) : null;

        // Check if exists by plate or phone
        $found = null;
        if ($plate !== '') {
            $select->execute([$plate]);
            $found = $select->fetch();
        }
        if (!$found) {
            $selectPhone = $pdo->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
            $selectPhone->execute([$phone]);
            $found = $selectPhone->fetch();
        }
        if ($found) {
            // Update
            $uid = $found['id'];
            $update->execute([$full, $phone, $email, $car, $notes, $lastService, $uid]);
            $updated++;

            $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], 'update_customer_via_import', "id={$uid}, phone={$phone}", $_SERVER['REMOTE_ADDR'] ?? '']);
        } else {
            // Insert
            try {
                $insert->execute([$full, $phone, $email, $plate, $car, $notes, $_SESSION['user_id'], $lastService]);
                $inserted++;
                $newId = $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], 'create_customer_via_import', "id={$newId}, phone={$phone}", $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (PDOException $e) {
                $failed++;
                $failures[] = "Row {$rowNo}: DB error - " . $e->getMessage();
            }
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['import_summary'] = ['error' => 'Import failed: ' . $e->getMessage()];
    fclose($handle);
    header('Location: customers.php');
    exit;
}

fclose($handle);

$_SESSION['import_summary'] = ['inserted' => $inserted, 'updated' => $updated, 'failed' => $failed, 'failures' => $failures];
header('Location: customers.php');
exit;
?>