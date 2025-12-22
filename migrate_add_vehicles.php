<?php
require 'config.php';
try {
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
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>