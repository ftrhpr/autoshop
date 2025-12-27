<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'API test working',
    'time' => date('Y-m-d H:i:s'),
    'params' => $_GET
]);
?>