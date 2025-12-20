<?php
require 'config.php';

echo "Checking for orphaned invoice customer references...\n\n";

try {
    // Find invoices with customer_id that doesn't exist in customers table
    $stmt = $pdo->query("
        SELECT i.id as invoice_id, i.customer_id, i.customer_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.customer_id IS NOT NULL AND c.id IS NULL
    ");
    $orphanedInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orphanedInvoices) . " invoices with invalid customer references:\n";
    foreach ($orphanedInvoices as $invoice) {
        echo "- Invoice ID {$invoice['invoice_id']}: references non-existent customer ID {$invoice['customer_id']} ({$invoice['customer_name']})\n";
    }

    if (count($orphanedInvoices) > 0) {
        echo "\nSetting customer_id to NULL for these invoices...\n";
        foreach ($orphanedInvoices as $invoice) {
            $stmt = $pdo->prepare("UPDATE invoices SET customer_id = NULL WHERE id = ?");
            $stmt->execute([$invoice['invoice_id']]);
            echo "Fixed invoice ID {$invoice['invoice_id']}\n";
        }
    }

    echo "\nCleanup completed!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>