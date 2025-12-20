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
        echo "Checking which ones are referenced by invoices...\n";

        // Check which customers are referenced by invoices
        $referencedCustomers = [];
        foreach ($emptyPlateCustomers as $customer) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?");
            $stmt->execute([$customer['id']]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                $referencedCustomers[] = $customer['id'];
                echo "Customer ID {$customer['id']} is referenced by {$result['count']} invoices - keeping it\n";
            } else {
                echo "Customer ID {$customer['id']} is not referenced - can be deleted\n";
            }
        }

        if (count($referencedCustomers) > 0) {
            echo "\nKeeping all referenced customers. Only deleting unreferenced ones...\n";
            $customersToDelete = array_filter($emptyPlateCustomers, function($customer) use ($referencedCustomers) {
                return !in_array($customer['id'], $referencedCustomers);
            });
        } else {
            echo "\nNo customers are referenced. Keeping the first one and deleting the rest...\n";
            $customersToDelete = array_slice($emptyPlateCustomers, 1);
        }

        foreach ($customersToDelete as $customer) {
            $id = $customer['id'];
            echo "Deleting unreferenced customer ID: $id\n";
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    echo "\nFix completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>