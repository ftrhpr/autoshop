<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

// Test invoice update
$invoiceId = 1; // Change this to a real invoice ID
$partName = 'Test Part'; // Change this to match a part in the invoice
$newPrice = 100.50;
$notes = 'Updated via test script';

try {
    // Get current invoice items
    $invoiceQuery = $pdo->prepare("SELECT items FROM invoices WHERE id = ?");
    $invoiceQuery->execute([$invoiceId]);
    $invoice = $invoiceQuery->fetch();

    if (!$invoice) {
        die('Invoice not found');
    }

    $items = json_decode($invoice['items'], true) ?: [];
    $updated = false;

    // Find and update the matching item
    foreach ($items as &$item) {
        if (isset($item['name']) && trim($item['name']) === trim($partName)) {
            $item['price_part'] = $newPrice;
            $item['notes'] = $notes;
            $updated = true;
            break;
        }
    }

    if ($updated) {
        // Recalculate totals
        $partsTotal = 0;
        $serviceTotal = 0;

        foreach ($items as $item) {
            $qty = floatval($item['qty'] ?? 1);
            $partPrice = floatval($item['price_part'] ?? 0);
            $servicePrice = floatval($item['price_service'] ?? 0);

            $partsTotal += $partPrice * $qty;
            $serviceTotal += $servicePrice * $qty;
        }

        $grandTotal = $partsTotal + $serviceTotal;

        // Update the invoice
        $updateInvoice = $pdo->prepare("
            UPDATE invoices
            SET items = ?, parts_total = ?, grand_total = ?
            WHERE id = ?
        ");
        $updateInvoice->execute([
            json_encode($items),
            $partsTotal,
            $grandTotal,
            $invoiceId
        ]);

        echo "Invoice updated successfully!<br>";
        echo "Parts Total: $partsTotal<br>";
        echo "Grand Total: $grandTotal<br>";
    } else {
        echo "Part not found in invoice";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>