<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Support both session auth and user_id param for mobile
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$other_id = $_GET['other_id'] ?? null;
$group_id = $_GET['group_id'] ?? null; // Support group fetching
// If other_id looks like "group_123", handle it implicitly? No, strict param is better, but frontend might send "group_123" as id?
// No, current frontend implementation sends proper object logic. But let's check input.
// Actually, in `openChat` implementation in frontend, we did `...g, isGroup: true`. 
// But the fetch call was `get_private_messages.php?other_id=${peer.id}`. 
// If it's a group, peer.id is the group ID. So let's check `isGroup` flag or treat other_id as group_id if needed?
// Frontend: `get_private_messages.php?other_id=${peer.id}&_t=${Date.now()}`
// Since we don't pass `is_group` param in the URL in `openChat`, we have a problem. 
// However, group IDs might clash with User IDs.
// FIX: Update Frontend `openChat` to pass `group_id` instead of `other_id` if it's a group.

// For now, let's assume if we receive `group_id` GET param, we use it.
if (isset($_GET['group_id'])) {
    $group_id = $_GET['group_id'];
} 

// Wait, I need to fix the frontend `openChat` first to pass `group_id` if it is a group!
// But assuming frontend passes `group_id` correctly:

if ($group_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_id, m.group_id, m.message, m.created_at, m.is_approved, m.is_read, m.is_delivered, m.is_deleted, m.reactions, m.reply_to,
                   u.username as sender_name, u.profile_picture as sender_pic
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$group_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages, 'is_group' => true]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

if (!$other_id) {
    echo json_encode(['success' => false, 'message' => 'Missing peer ID']);
    exit();
}

if (isset($_GET['mark_read_only'])) {
    try {
        $pdo->prepare("UPDATE messages SET is_read = 1, is_delivered = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
            ->execute([$other_id, $user_id]);
        echo json_encode(['success' => true]);
        exit();
    } catch (PDOException $e) {
        exit();
    }
}

try {
    // Migration logic moved to database_setup.php for production stability.

    // Mark incoming messages as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
        ->execute([$other_id, $user_id]);

    // Verify limit and offset for pagination
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $sql = "
        SELECT id, sender_id, receiver_id, message, created_at, is_approved, is_read, is_delivered, is_deleted, reactions, reply_to
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    
    // We order by DESC for pagination (newest first), then we will reverse it back for the frontend (oldest first)
    $stmt->execute([$user_id, $other_id, $other_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reverse to show oldest -> newest
    $messages = array_reverse($messages);

    // Get Peer Status
    $stmtPeer = $pdo->prepare("SELECT last_active_at FROM users WHERE id = ?");
    $stmtPeer->execute([$other_id]);
    $peerStatus = $stmtPeer->fetchColumn();

    echo json_encode(['success' => true, 'messages' => $messages, 'last_active_at' => $peerStatus]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
