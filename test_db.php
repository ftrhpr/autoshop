<?php
$config = include 'config.php';
try {
    $pdo = new PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM parts');
    $parts = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM labors');
    $labors = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Parts: ' . $parts['count'] . ', Labors: ' . $labors['count'] . PHP_EOL;
} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage() . PHP_EOL;
}
?>