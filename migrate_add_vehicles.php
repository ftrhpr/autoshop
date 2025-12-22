<?php
require 'config.php';
try {
    // Check if vehicles table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'vehicles'")->fetch();
    if (!$tableExists) {
        // Create vehicles table
        $pdo->exec("
            CREATE TABLE vehicles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                plate_number VARCHAR(20) NOT NULL,
                car_mark VARCHAR(100),
                vin VARCHAR(50),
                mileage VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                UNIQUE KEY unique_plate (plate_number)
            )
        ");

        // Migrate existing customer data to vehicles
        $stmt = $pdo->query("SELECT id, plate_number, car_mark FROM customers WHERE plate_number IS NOT NULL AND plate_number != ''");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $insertStmt = $pdo->prepare("INSERT INTO vehicles (customer_id, plate_number, car_mark) VALUES (?, ?, ?)");
        foreach ($customers as $customer) {
            $insertStmt->execute([$customer['id'], $customer['plate_number'], $customer['car_mark']]);
        }

        echo "Vehicles table created and data migrated.\n";
    } else {
        echo "Vehicles table already exists.\n";
    }

    // Check if vehicle_id column exists in invoices
    $columnExists = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'vehicle_id'")->fetch();
    if (!$columnExists) {
        // Add vehicle_id to invoices table
        $pdo->exec("ALTER TABLE invoices ADD COLUMN vehicle_id INT AFTER service_manager_id");

        // Update existing invoices to set vehicle_id based on customer data
        $pdo->exec("
            UPDATE invoices i
            JOIN customers c ON i.customer_id = c.id
            JOIN vehicles v ON c.id = v.customer_id AND i.plate_number = v.plate_number
            SET i.vehicle_id = v.id
            WHERE i.customer_id IS NOT NULL AND i.plate_number IS NOT NULL
        ");

        echo "Vehicle_id column added to invoices and data updated.\n";
    } else {
        echo "Vehicle_id column already exists in invoices.\n";
    }

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>