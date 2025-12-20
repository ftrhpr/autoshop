<?php
require 'config.php';

echo "Fixing customers with empty plate numbers...\n\n";

try {
    // Find customers with empty plate numbers
    $stmt = $pdo->query("SELECT id, full_name FROM customers WHERE plate_number = '' OR plate_number IS NULL");
    $emptyPlateCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($emptyPlateCustomers) . " customers with empty plate numbers:\n";
    foreach ($emptyPlateCustomers as $customer) {
        echo "- ID {$customer['id']}: {$customer['full_name']}\n";
    }

    if (count($emptyPlateCustomers) > 1) {
        echo "\nMultiple customers with empty plate numbers found.\n";
        echo "Keeping the first one (ID: {$emptyPlateCustomers[0]['id']}) and deleting the rest...\n";

        // Keep the first customer, delete the rest
        for ($i = 1; $i < count($emptyPlateCustomers); $i++) {
            $id = $emptyPlateCustomers[$i]['id'];
            echo "Deleting customer ID: $id\n";

            // Update any invoices that reference this customer to use the first customer
            $stmt = $pdo->prepare("UPDATE invoices SET customer_id = ? WHERE customer_id = ?");
            $stmt->execute([$emptyPlateCustomers[0]['id'], $id]);

            // Delete the duplicate customer
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    echo "\nFix completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>