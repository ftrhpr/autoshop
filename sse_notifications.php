<?php
require 'config.php';
// SSE endpoint to push invoice notifications to admin/manager users
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

set_time_limit(0);
ignore_user_abort(true);

$uid = (int)$_SESSION['user_id'];
$lastNotifyId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

function sendEvent($name, $data){
    echo "event: {$name}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    @ob_flush(); @flush();
}

// send initial ping and retry
echo "retry: 5000\n\n";
@ob_flush(); @flush();

// If notifications table missing, send an error event and exit gracefully
$hasNotifications = (bool)$pdo->query("SHOW TABLES LIKE 'invoice_notifications'")->fetch();
if (!$hasNotifications){
    sendEvent('error', ['message' => 'invoice_notifications table missing']);
    exit;
}

while (!connection_aborted()) {
    try {
        // fetch new notifications for this user
        $stmt = $pdo->prepare('SELECT n.id AS notify_id, i.id AS invoice_id, i.created_at, i.customer_name, i.plate_number, i.grand_total FROM invoice_notifications n JOIN invoices i ON i.id = n.invoice_id WHERE n.user_id = ? AND n.id > ? AND n.seen_at IS NULL ORDER BY n.id ASC');
        $stmt->execute([$uid, $lastNotifyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)){
            foreach ($rows as $r){
                $lastNotifyId = max($lastNotifyId, (int)$r['notify_id']);
                sendEvent('notification', $r);
            }
            // Also send a count update
            $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM invoice_notifications WHERE user_id = ? AND seen_at IS NULL');
            $stmt2->execute([$uid]);
            $cnt = (int)$stmt2->fetchColumn();
            sendEvent('count', ['unread' => $cnt]);
        } else {
            // keep-alive comment
            echo ": ping\n\n";
            @ob_flush(); @flush();
        }

    } catch (Exception $e) {
        // If an error occurs, send an error event and break
        sendEvent('error', ['message' => $e->getMessage()]);
        break;
    }

    sleep(2);
}

// connection closed
exit;