<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Support both session auth and user_id param for mobile
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Lazy Setup
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_approved TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sender_id),
        INDEX (receiver_id),
        INDEX (is_approved)
    )");

    // Migration logic for existing tables
    try {
        $stmt_check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_approved'");
        if (!$stmt_check->fetch()) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
            $pdo->exec("CREATE INDEX idx_is_approved ON messages(is_approved)");
        }
        
        // Check for is_delivered
        $stmt_check_dlvr = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_delivered'");
        if (!$stmt_check_dlvr->fetch()) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
        }
    } catch (Exception $e) {}

    // Proactive Auto-Approval: If two users follow each other, mark all their messages as approved.
    try {
        $pdo->exec("
            UPDATE messages m
            JOIN subscriptions s1 ON m.sender_id = s1.subscriber_id AND m.receiver_id = s1.channel_id
            JOIN subscriptions s2 ON m.sender_id = s2.channel_id AND m.receiver_id = s2.subscriber_id
            SET m.is_approved = 1
            WHERE m.is_approved = 0
        ");
    } catch (Exception $e) {}

    // Lazy Add last_active_at to users
    try {
        $stmt_check_usr = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_active_at'");
        if (!$stmt_check_usr->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_active_at TIMESTAMP NULL DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Update MY activity
    $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE id = ?")->execute([$user_id]);

    // 1. Get MESSAGE REQUESTS - pending messages sent TO me (I haven't approved yet)
    $stmtRequests = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.profile_picture,
            (SELECT message FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_approved = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_approved = 0 ORDER BY created_at DESC LIMIT 1) as last_time,
            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_approved = 0) as message_count,
            u.last_active_at
        FROM users u
        WHERE EXISTS (
            SELECT 1 FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_approved = 0
        )
        ORDER BY last_time DESC
    ");
    $stmtRequests->execute([$user_id, $user_id, $user_id, $user_id]);
    $requests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get APPROVED conversations (at least one approved message exists)
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.profile_picture,
            m.message as last_message,
            m.created_at as last_time,
            m.is_approved,
            u.last_active_at
        FROM users u
        JOIN (
            SELECT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END as other_id,
                MAX(id) as max_id
            FROM messages
            WHERE (sender_id = ? OR receiver_id = ?)
            AND is_approved = 1
            GROUP BY other_id
        ) latest ON u.id = latest.other_id
        JOIN messages m ON m.id = latest.max_id
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get PENDING SENT (requests I sent that are waiting for others to approve)
    $stmtPendingSent = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.profile_picture,
            (SELECT message FROM messages WHERE sender_id = ? AND receiver_id = u.id AND is_approved = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE sender_id = ? AND receiver_id = u.id AND is_approved = 0 ORDER BY created_at DESC LIMIT 1) as last_time,
            'pending_sent' as status,
            u.last_active_at
        FROM users u
        WHERE EXISTS (
            SELECT 1 FROM messages m WHERE m.sender_id = ? AND m.receiver_id = u.id AND m.is_approved = 0
        )
        AND NOT EXISTS (
            SELECT 1 FROM messages m2 
            WHERE ((m2.sender_id = ? AND m2.receiver_id = u.id) OR (m2.sender_id = u.id AND m2.receiver_id = ?))
            AND m2.is_approved = 1
        )
        ORDER BY last_time DESC
    ");
    $stmtPendingSent->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $pendingSent = $stmtPendingSent->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get GROUPS
    // First, ensure tables exist (Lazy Check since create_group might not have run yet)
    try {
        $pdo->query("SELECT 1 FROM chat_groups LIMIT 1");
        // Tables exist, fetch groups
        $stmtGroups = $pdo->prepare("
            SELECT 
                g.id, 
                g.name, 
                g.picture,
                (SELECT message FROM messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) as last_time,
                (SELECT COUNT(*) FROM chat_group_members WHERE group_id = g.id) as member_count
            FROM chat_groups g
            JOIN chat_group_members gm ON g.id = gm.group_id
            WHERE gm.user_id = ?
            ORDER BY last_time DESC
        ");
        $stmtGroups->execute([$user_id]);
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist yet, empty groups
        $groups = [];
    }

    echo json_encode([
        'success' => true, 
        'requests' => $requests,
        'conversations' => $conversations,
        'groups' => $groups ?? [],
        'pending_sent' => $pendingSent
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
