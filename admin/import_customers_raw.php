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

$namesData = trim($_POST['names'] ?? '');
$phonesData = trim($_POST['phones'] ?? '');
if (empty($namesData) || empty($phonesData)) {
    $_SESSION['import_summary'] = ['error' => 'Both names and phones must be provided.'];
    header('Location: customers.php');
    exit;
}

$names = array_map('trim', explode("\n", $namesData));
$phones = array_map('trim', explode("\n", $phonesData));
$lines = array_map(null, $names, $phones); // Pair them, padding with null if uneven
$validLines = array_filter($lines, function($pair) { return !empty($pair[1]); }); // Keep only pairs with phone
if (empty($validLines)) {
    $_SESSION['import_summary'] = ['error' => 'No valid data with phones provided.'];
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

    foreach ($validLines as $rowNo => $pair) {
        $rowNo++; // 1-based
        if ($rowNo > $maxRows) {
            $failures[] = "Row limit reached at {$maxRows}";
            break;
        }

        $full = $pair[0] ?? '';
        $phone = $pair[1] ?? '';

        if (empty($phone)) continue; // Skip if no phone

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