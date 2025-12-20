<?php
// Database configuration
$host = 'localhost'; // Usually localhost for cPanel
$dbname = 'otoexpre_managers'; // Replace with your database name
$username = 'otoexpre_managers'; // Replace with your database username
$password = '5[VMnC@C8-!Stou6'; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session
session_start();
?>