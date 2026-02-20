<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents('php://input'), true);

// Support both session auth and sender_id param for mobile
$sender_id = $_SESSION['user_id'] ?? $data['sender_id'] ?? null;

if (!$sender_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$receiver_id = $data['receiver_id'] ?? 0;
$group_id = $data['group_id'] ?? null;
$message = $data['message'] ?? null;

if ((!$receiver_id && !$group_id) || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

try {
    // Check if table exists, if not create it (Lazy Setup)
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL DEFAULT 0,
        group_id INT DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_approved TINYINT(1) DEFAULT 0,
        reply_to TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sender_id),
        INDEX (receiver_id),
        INDEX (group_id),
        INDEX (is_approved)
    )");

    // Ensure groups tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        picture VARCHAR(255),
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('member', 'admin') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(group_id, user_id)
    )");

    // Migration: add needed columns if they don't exist
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_approved'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'reply_to'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN reply_to TEXT DEFAULT NULL");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'group_id'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id, ADD INDEX (group_id)");
        }
    } catch (Exception $e) {}

    // GROUP LOGIC
    if ($group_id) {
        $isApproved = 1; // Group messages are always approved
        $isNewRequest = false;

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, group_id, message, is_approved, reply_to) VALUES (?, 0, ?, ?, 1, ?)");
        $stmt->execute([$sender_id, $group_id, $message, $data['reply_to'] ?? null]);
        $messageId = $pdo->lastInsertId();

    } else {
        // 1-on-1 LOGIC
        
        // Check if there is an existing approved conversation
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND is_approved = 1");
        $stmtCheck->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
        $isApproved = $stmtCheck->fetchColumn() > 0;

        // IF NOT approved yet, check if they are mutual followers (mutual follow = auto-approve)
        if (!$isApproved && $sender_id !== $receiver_id) {
            $stmtMutual = $pdo->prepare("
                SELECT COUNT(*) FROM subscriptions s1
                JOIN subscriptions s2 ON s1.subscriber_id = s2.channel_id AND s1.channel_id = s2.subscriber_id
                WHERE s1.subscriber_id = ? AND s1.channel_id = ?
            ");
            $stmtMutual->execute([$sender_id, $receiver_id]);
            if ($stmtMutual->fetchColumn() > 0) {
                $isApproved = true;
            }
        }

        // Check if a pending request from me to them already exists
        $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_approved = 0");
        $stmtPending->execute([$sender_id, $receiver_id]);
        $hasPending = $stmtPending->fetchColumn() > 0;

        // If NOT approved AND already has a pending message -> BLOCK (only 1 request allowed)
        // if (!$isApproved && $hasPending) {
        //     echo json_encode(['success' => false, 'message' => 'Request pending. Wait for approval before sending more messages.', 'is_pending' => true]);
        //     exit();
        // }

        // Insert the message
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_approved, reply_to) VALUES (?, ?, ?, ?, ?)");
        // FORCE APPROVE FOR DEBUGGING
        $stmt->execute([$sender_id, $receiver_id, $message, 1, $data['reply_to'] ?? null]);
        $messageId = $pdo->lastInsertId();

        // Trigger Notification if this is the first request (not approved yet)
        $isNewRequest = !$isApproved && !$hasPending;
        if ($isNewRequest) {
            require_once 'createNotification.php';
            // Ensure username is fetched if session is missing (mobile api call)
            if (!isset($_SESSION['username'])) {
                 $stmtU = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                 $stmtU->execute([$sender_id]);
                 $senderName = $stmtU->fetchColumn() ?? 'Someone';
            } else {
                 $senderName = $_SESSION['username'];
            }
            
            createNotification(
                $pdo, 
                $receiver_id, 
                'new_message', 
                $sender_id, 
                $messageId, 
                'message', 
                "$senderName sent you a new message"
            );
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message_id' => $messageId,
        'is_request' => $isNewRequest,
        'is_approved' => $isApproved
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

