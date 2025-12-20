<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Unauthorized. Only admins can run migrations.";
    exit;
}

echo "<pre>Starting migration...\n";

try {
    // Check if customers table exists
    $exists = $pdo->query("SHOW TABLES LIKE 'customers'")->fetch();
    if ($exists) {
        echo "- customers table already exists.\n";
    } else {
        echo "- Creating customers table...\n";
        $sql = "CREATE TABLE customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(200),
            phone VARCHAR(32),
            email VARCHAR(150),
            plate_number VARCHAR(20) UNIQUE NOT NULL,
            car_mark VARCHAR(100),
            notes TEXT,
            last_service_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
        echo "  -> customers table created.\n";
    }

    // Check invoices table for customer_id
    $col = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'customer_id'")->fetch();
    if ($col) {
        echo "- invoices.customer_id column already exists.\n";
    } else {
        echo "- Adding customer_id column to invoices...\n";
        $pdo->exec("ALTER TABLE invoices ADD COLUMN customer_id INT NULL AFTER service_manager");
        echo "  -> column added.\n";

        // Try to add FK
        try {
            $pdo->exec("ALTER TABLE invoices ADD CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL");
            echo "  -> foreign key added.\n";
        } catch (PDOException $e) {
            echo "  -> warning: could not add foreign key: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Helpful note
echo "<p><a href=\"customers.php\">Back to Customers</a></p>";
?>