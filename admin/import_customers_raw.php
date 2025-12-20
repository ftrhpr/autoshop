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

$rawData = trim($_POST['raw_data'] ?? '');
if (empty($rawData)) {
    $_SESSION['import_summary'] = ['error' => 'No raw data provided.'];
    header('Location: customers.php');
    exit;
}

$lines = explode("\n", $rawData);
if (empty($lines)) {
    $_SESSION['import_summary'] = ['error' => 'No valid lines in raw data.'];
    header('Location: customers.php');
    exit;
}

$maxRows = 2000; // safety limit
$inserted = 0;
$updated = 0;
$failed = 0;
$failures = [];
$rowNo = 0;

$pdo->beginTransaction();
try {
    $select = $pdo->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO customers (full_name, phone, created_by) VALUES (?, ?, ?)');
    $update = $pdo->prepare('UPDATE customers SET full_name = ? WHERE id = ?');

    foreach ($lines as $line) {
        $rowNo++;
        if ($rowNo > $maxRows) {
            $failures[] = "Row limit reached at {$maxRows}";
            break;
        }

        $line = trim($line);
        if (empty($line)) continue;

        $parts = str_getcsv($line, ',', '"');
        if (count($parts) < 2) {
            $failed++;
            $failures[] = "Row {$rowNo}: invalid format, expected full_name,phone";
            continue;
        }

        $full = trim($parts[0]);
        $phone = trim($parts[1]);

        if (empty($phone)) {
            $failed++;
            $failures[] = "Row {$rowNo}: empty phone number";
            continue;
        }

        // Ensure UTF-8
        $full = mb_convert_encoding($full, 'UTF-8', mb_detect_encoding($full, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true) ?: 'UTF-8');
        $phone = mb_convert_encoding($phone, 'UTF-8', mb_detect_encoding($phone, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true) ?: 'UTF-8');

        // Check if exists by phone
        $select->execute([$phone]);
        $found = $select->fetch();
        if ($found) {
            // Update
            $uid = $found['id'];
            $update->execute([$full, $uid]);
            $updated++;
        } else {
            // Insert
            try {
                $insert->execute([$full, $phone, $_SESSION['user_id']]);
                $inserted++;
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
    header('Location: customers.php');
    exit;
}

$_SESSION['import_summary'] = ['inserted' => $inserted, 'updated' => $updated, 'failed' => $failed, 'failures' => $failures];
header('Location: customers.php');
exit;
?>