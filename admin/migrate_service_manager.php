<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Unauthorized. Only admins can run migrations.";
    exit;
}

echo "<pre>Starting service_manager_id migration...\n";

try {
    // Check if column exists
    $col = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'service_manager_id'")->fetch();
    if ($col) {
        echo "- invoices.service_manager_id already exists.\n";
    } else {
        echo "- Adding service_manager_id column to invoices...\n";
        $pdo->exec("ALTER TABLE invoices ADD COLUMN service_manager_id INT NULL AFTER service_manager");
        echo "  -> column added.\n";

        // Try to add FK
        try {
            $pdo->exec("ALTER TABLE invoices ADD CONSTRAINT fk_invoices_service_manager FOREIGN KEY (service_manager_id) REFERENCES users(id) ON DELETE SET NULL");
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

echo "<p><a href=\"../admin/index.php\">Back to Admin</a></p>";
?>