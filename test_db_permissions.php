<?php
require_once 'config.php';

echo "Testing database update permissions...\n";

try {
    // Test SELECT
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM part_pricing_requests");
    $count = $stmt->fetch()['count'];
    echo "SELECT works: $count records found\n";

    // Test UPDATE on a test record (if exists)
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id FROM part_pricing_requests LIMIT 1");
        $id = $stmt->fetch()['id'];
        echo "Found test record ID: $id\n";

        // Try to update
        $updateStmt = $pdo->prepare("UPDATE part_pricing_requests SET updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$id]);
        $affected = $updateStmt->rowCount();

        echo "UPDATE test result: $affected rows affected\n";
        if ($result) {
            echo "UPDATE permission: OK\n";
        } else {
            echo "UPDATE permission: FAILED\n";
        }
    } else {
        echo "No records to test UPDATE on\n";
    }

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>