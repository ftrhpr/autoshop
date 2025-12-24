<?php
require 'config.php';

echo "Running database setup and migration...\n";

try {
    // Create oil tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS oil_brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS oil_viscosities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            viscosity VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS oil_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            viscosity_id INT NOT NULL,
            package_type ENUM('canned', '5lt', '4lt', '1lt') NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES oil_brands(id) ON DELETE CASCADE,
            FOREIGN KEY (viscosity_id) REFERENCES oil_viscosities(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_oil_price (brand_id, viscosity_id, package_type)
        )
    ");

    echo "Oil tables created successfully.\n";

    // Add oils column to invoices table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM invoices LIKE 'oils'");
    $stmt->execute();
    $column = $stmt->fetch();

    if (!$column) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN oils JSON NULL");
        echo "Added oils column to invoices table.\n";
    } else {
        echo "Oils column already exists in invoices table.\n";
    }

    // Insert some sample data if tables are empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM oil_brands");
    $brandCount = $stmt->fetchColumn();

    if ($brandCount == 0) {
        // Insert sample brands
        $pdo->exec("INSERT INTO oil_brands (name) VALUES
            ('Castrol'),
            ('Mobil'),
            ('Shell'),
            ('Total'),
            ('ELF')");

        // Insert sample viscosities
        $pdo->exec("INSERT INTO oil_viscosities (viscosity, description) VALUES
            ('5W-30', 'Multi-grade synthetic oil'),
            ('10W-40', 'Semi-synthetic oil'),
            ('15W-40', 'Mineral oil'),
            ('0W-20', 'Full synthetic low viscosity'),
            ('5W-40', 'Full synthetic high performance')");

        echo "Inserted sample oil data.\n";
    }

    echo "Database setup completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>