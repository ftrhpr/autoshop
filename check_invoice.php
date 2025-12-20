<?php
require 'config.php';

echo "Checking invoice ID 19...\n\n";

try {
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([19]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        echo "Invoice found:\n";
        echo json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        // Check if items is valid JSON
        if (!empty($invoice['items'])) {
            $items = json_decode($invoice['items'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "Items (decoded):\n";
                echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "Items JSON decode error: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "No items data\n";
        }

        // Check customer if exists
        if (!empty($invoice['customer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
            $stmt->execute([$invoice['customer_id']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($customer) {
                echo "\nCustomer data:\n";
                echo json_encode($customer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "\nCustomer not found\n";
            }
        }

        // Check service manager if exists
        if (!empty($invoice['service_manager_id'])) {
            $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->execute([$invoice['service_manager_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo "\nService manager: " . $user['username'] . "\n";
            } else {
                echo "\nService manager not found\n";
            }
        }

    } else {
        echo "Invoice ID 19 not found in database\n";

        // Check what invoices do exist
        $stmt = $pdo->query('SELECT id, customer_name, creation_date FROM invoices ORDER BY id DESC LIMIT 10');
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nRecent invoices:\n";
        foreach ($invoices as $inv) {
            echo "ID {$inv['id']}: {$inv['customer_name']} - {$inv['creation_date']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>