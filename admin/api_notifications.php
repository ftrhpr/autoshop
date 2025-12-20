<?php
require '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action == 'get_unread') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($notifications);
} elseif ($action == 'mark_as_read') {
    $notification_id = $_POST['id'] ?? null;
    if ($notification_id) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Notification ID is required']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>