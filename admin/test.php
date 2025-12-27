<?php
// Simple test file to check PHP and config
echo "PHP is working\n";
echo "PHP version: " . phpversion() . "\n";

if (file_exists('../config.php')) {
    echo "config.php exists\n";
    require '../config.php';
    echo "config.php loaded successfully\n";
    echo "Session started: " . (session_id() ? 'yes' : 'no') . "\n";
    echo "PDO available: " . (class_exists('PDO') ? 'yes' : 'no') . "\n";
} else {
    echo "config.php not found\n";
}
?>