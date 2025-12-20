<?php
// Simple test to check if PHP is working
echo "PHP is working on server: " . phpversion() . "\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . $_SERVER['HTTP_HOST'] . "\n";

// Test database connection
require 'config.php';
echo "Database connection successful\n";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
?>