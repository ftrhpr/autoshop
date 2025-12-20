<?php
// Simple diagnostic endpoint to help identify why the site might be failing.
require 'config.php';
header('Content-Type: application/json');

$out = [
    'php_version' => phpversion(),
    'utc_now' => gmdate('Y-m-d H:i:s'),
    'db' => [
        'connected' => true,
        'dbname' => null,
        'error' => null,
    ],
    'tables' => [],
    'files' => [],
];

// DB details
try {
    $stmt = $pdo->query('SELECT DATABASE() as db');
    $out['db']['dbname'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $out['db']['connected'] = false;
    $out['db']['error'] = $e->getMessage();
}

// Check key tables
$checkTables = ['invoices','users','invoice_notifications'];
foreach ($checkTables as $t){
    try {
        $r = $pdo->query("SHOW TABLES LIKE '".addslashes($t)."'")->fetch();
        $out['tables'][$t] = $r ? true : false;
    } catch (Exception $e) {
        $out['tables'][$t] = 'error: '.$e->getMessage();
    }
}

// Check that notify mp3 exists
$out['files']['notify_mp3'] = file_exists(__DIR__.'/assets/sounds/notify.mp3');
$out['files']['notify_php'] = file_exists(__DIR__.'/assets/sounds/notify.php');

// Check basic endpoints availability (relative to same host)
function checkUrl($url){
    $opts = ['http'=>['method'=>'GET','timeout'=>2]];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return ['ok'=>false];
    return ['ok'=>true];
}

$base = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']);
$out['endpoints'] = [
    'api_new_invoices' => checkUrl($base.'/api_new_invoices.php'),
    'api_invoices_since' => checkUrl($base.'/api_invoices_since.php?last_id=0'),
    'sse_notifications' => checkUrl($base.'/sse_notifications.php'),
];

echo json_encode($out, JSON_PRETTY_PRINT);
