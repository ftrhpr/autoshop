<?php
// Test script to create a sample invoice with parts that need pricing
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please log in first to run this test.\n");
}

try {
    // Create a test invoice with parts that have zero prices
    $testItems = [
        [
            'name' => 'Brake Pads',
            'description' => 'Front brake pads for Toyota Camry',
            'qty' => 1,
            'price_part' => 0, // This will trigger a pricing request
            'db_type' => 'part'
        ],
        [
            'name' => 'Oil Filter',
            'description' => 'Standard oil filter',
            'qty' => 1,
            'price_part' => 0, // This will trigger a pricing request
            'db_type' => 'part'
        ],
        [
            'name' => 'Brake Fluid',
            'description' => 'DOT 4 brake fluid',
            'qty' => 1,
            'price_part' => 25.50, // This has a price, won't trigger request
            'db_type' => 'part'
        ]
    ];

    // Insert test invoice
    $stmt = $pdo->prepare("
        INSERT INTO invoices (creation_date, customer_name, phone, car_mark, plate_number, mileage, items, parts_total, service_total, grand_total, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        date('Y-m-d H:i:s'), // creation_date
        'Test Customer',
        '+995-555-123456', // phone
        'Toyota Camry',
        'TEST-001',
        '150000', // mileage
        json_encode($testItems),
        0, // parts_total
        0, // service_total
        0, // grand_total
        $_SESSION['user_id']
    ]);

    $invoiceId = $pdo->lastInsertId();

    echo "<h2>Test invoice created successfully!</h2>";
    echo "<p>Invoice ID: <strong>$invoiceId</strong></p>";
    echo "<p>This invoice contains parts with zero prices that should create pricing requests.</p>";
    echo "<p><a href='admin/parts_collection.php' class='bg-blue-600 text-white px-4 py-2 rounded'>Check Parts Collection Dashboard</a></p>";

    // Also trigger the parts collection request creation manually since save_invoice.php might not be called
    foreach ($testItems as $it) {
        if (!empty($it['db_type']) && $it['db_type'] === 'part' &&
            (empty($it['price_part']) || floatval($it['price_part']) == 0)) {

            $partName = trim($it['name']);
            $quantity = floatval($it['qty'] ?? 1);

            // Check if pricing request already exists
            $stmt = $pdo->prepare('
                SELECT id FROM part_pricing_requests
                WHERE invoice_id = ? AND part_name = ? AND status != "cancelled"
                LIMIT 1
            ');
            $stmt->execute([$invoiceId, $partName]);
            $existingRequest = $stmt->fetch();

            if (!$existingRequest) {
                // Create new pricing request
                $insRequest = $pdo->prepare('
                    INSERT INTO part_pricing_requests
                    (invoice_id, part_name, part_description, requested_quantity, vehicle_make, vehicle_model, requested_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $insRequest->execute([
                    $invoiceId,
                    $partName,
                    $it['description'] ?? '',
                    $quantity,
                    'Toyota',
                    'Camry',
                    $_SESSION['user_id']
                ]);

                echo "<p>✅ Created pricing request for: <strong>$partName</strong></p>";
            }
        }
    }

} catch (Exception $e) {
    echo "<h2>Error creating test invoice:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>