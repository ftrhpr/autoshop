<?php
require_once 'config.php';

try {
    // Check if table exists
    $stmt = $pdo->query('SHOW TABLES LIKE "part_pricing_requests"');
    $tableExists = $stmt->rowCount() > 0;
    echo "Table exists: " . ($tableExists ? 'YES' : 'NO') . PHP_EOL;

    if ($tableExists) {
        // Check table structure
        $stmt = $pdo->query('DESCRIBE part_pricing_requests');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table columns: " . count($columns) . PHP_EOL;

        // Check for records
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM part_pricing_requests');
        $count = $stmt->fetch()['count'];
        echo "Total requests: $count" . PHP_EOL;

        // Check pending requests
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM part_pricing_requests WHERE status = "pending"');
        $pending = $stmt->fetch()['count'];
        echo "Pending requests: $pending" . PHP_EOL;

        // Show recent requests
        if ($count > 0) {
            $stmt = $pdo->query('SELECT id, part_name, status, created_at FROM part_pricing_requests ORDER BY created_at DESC LIMIT 5');
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo PHP_EOL . "Recent requests:" . PHP_EOL;
            foreach ($recent as $req) {
                echo "- ID {$req['id']}: {$req['part_name']} ({$req['status']}) - {$req['created_at']}" . PHP_EOL;
            }
        }
    } else {
        echo "Migration may not have been run. Please run migrate_add_parts_collection_system.php" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>