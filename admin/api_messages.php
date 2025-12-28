<?php
require_once '../config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_messages':
            // Get inbox and sent messages
            $inbox_stmt = $pdo->prepare("
                SELECT m.*, u.username as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.recipient_id = ?
                ORDER BY m.created_at DESC
            ");
            $inbox_stmt->execute([$user_id]);
            $inbox = $inbox_stmt->fetchAll();

            $sent_stmt = $pdo->prepare("
                SELECT m.*, u.username as recipient_name
                FROM messages m
                JOIN users u ON m.recipient_id = u.id
                WHERE m.sender_id = ?
                ORDER BY m.created_at DESC
            ");
            $sent_stmt->execute([$user_id]);
            $sent = $sent_stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'inbox' => $inbox,
                'sent' => $sent
            ]);
            break;

        case 'send':
            $input = json_decode(file_get_contents('php://input'), true);
            $recipient_id = (int)($input['recipient_id'] ?? 0);
            $subject = trim($input['subject'] ?? '');
            $body = trim($input['body'] ?? '');

            if (!$recipient_id || empty($body)) {
                echo json_encode(['success' => false, 'message' => 'მიმღები და შეტყობინება სავალდებულოა']);
                exit;
            }

            // Verify recipient exists and is allowed to receive messages
            $recipient_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('manager', 'user', 'parts_collection_manager')");
            $recipient_check->execute([$recipient_id]);
            if (!$recipient_check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'არასწორი მიმღები']);
                exit;
            }

            $insert_stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insert_stmt->execute([$user_id, $recipient_id, $subject, $body]);

            echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
            break;

        case 'mark_read':
            $input = json_decode(file_get_contents('php://input'), true);
            $message_id = (int)($input['message_id'] ?? 0);

            if (!$message_id) {
                echo json_encode(['success' => false, 'message' => 'შეტყობინების ID სავალდებულოა']);
                exit;
            }

            // Verify the message belongs to current user
            $check_stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND recipient_id = ?");
            $check_stmt->execute([$message_id, $user_id]);
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'შეტყობინება არ მოიძებნა']);
                exit;
            }

            $update_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $update_stmt->execute([$message_id]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'არასწორი მოქმედება']);
            break;
    }
} catch (PDOException $e) {
    error_log("Messages API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'მოხდა შეცდომა']);
}