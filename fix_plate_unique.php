<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Admin access required');
}

try {
    $pdo->exec('ALTER TABLE customers DROP INDEX plate_number');
    echo 'Unique constraint removed from plate_number.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>