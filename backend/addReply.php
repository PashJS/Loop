<?php
// backend/addReply.php - Add a reply to a comment
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to reply to comments.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$videoId = isset($input['video_id']) ? (int)$input['video_id'] : 0;
$parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : 0;
$comment = isset($input['comment']) ? trim($input['comment']) : '';
$userId = $_SESSION['user_id'];

if ($videoId <= 0 || $parentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid video ID or parent comment ID.'
    ]);
    exit;
}

if (empty($comment)) {
    echo json_encode([
        'success' => false,
        'message' => 'Reply cannot be empty.'
    ]);
    exit;
}

if (strlen($comment) > 1000) {
    echo json_encode([
        'success' => false,
        'message' => 'Reply is too long (max 1000 characters).'
    ]);
    exit;
}

// Moderate comment
require 'moderateComment.php';
$moderation = moderateComment($comment);
if ($moderation['blocked']) {
    echo json_encode([
        'success' => false,
        'message' => 'Your reply contains inappropriate language. Please revise your reply.'
    ]);
    exit;
}
$comment = $moderation['filtered'];

try {
    // Verify parent comment exists and belongs to the video
    $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND video_id = ?");
    $stmt->execute([$parentId, $videoId]);
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Parent comment not found.'
        ]);
        exit;
    }
    
    // Get parent comment author for notification
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$parentId]);
    $parentComment = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentAuthorId = $parentComment['user_id'] ?? null;
    // Also fetch parent author username for payload
    $parentAuthorName = null;
    if ($parentAuthorId) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$parentAuthorId]);
        $parentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $parentAuthorName = $parentUserRow['username'] ?? null;
    }

    // Get video creator id for permission checks
    $stmt = $pdo->prepare("SELECT user_id FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $videoCreatorId = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'] ?? null;
    
    // Insert reply
    $stmt = $pdo->prepare("INSERT INTO comments (user_id, video_id, comment, parent_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $videoId, $comment, $parentId]);
    $replyId = $pdo->lastInsertId();

    // Debug log: record reply creation (helpful during local troubleshooting)
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logEntry = sprintf("%s	REPLY_CREATED	id=%s	user=%s	video=%s	parent=%s	len=%d\n", date('c'), $replyId, $userId, $videoId, $parentId, strlen($comment));
    @file_put_contents($logDir . '/replies.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Create notification for parent comment author (if not replying to own comment)
    if ($parentAuthorId && $parentAuthorId != $userId) {
        require 'createNotification.php';
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $replier = $stmt->fetch(PDO::FETCH_ASSOC);
        $message = $replier['username'] . " replied to your comment";
        createNotification($pdo, $parentAuthorId, 'comment_reply', $userId, $parentId, 'comment', $message);
    }
    
    // Fetch the created reply with user info and pro settings
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.comment,
            c.parent_id,
            c.created_at,
            c.updated_at,
            u.id as user_id,
            u.username,
            u.profile_picture,
            u.is_pro,
            ps.comment_badge,
            ps.name_badge
            FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$replyId]);
    $newReply = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log the fetched reply row for debugging
    $logEntry = sprintf("%s\tREPLY_ROW\tid=%s\tparent=%s\tvideo=%s\trow=%s\n", date('c'), $newReply['id'] ?? 'null', $newReply['parent_id'] ?? 'null', $videoId, json_encode($newReply));
    @file_put_contents($logDir . '/replies.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Get parent comment info for reply chain
    $replyChain = [];
    $currentParentId = $parentId;
    $maxDepth = 10;
    
    for ($i = 0; $i < $maxDepth; $i++) {
        $stmt = $pdo->prepare("
            SELECT c.parent_id, u.username
            FROM comments c
            INNER JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$currentParentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$parent || !$parent['parent_id']) {
            break;
        }
        
        array_unshift($replyChain, $parent['username']);
        $currentParentId = $parent['parent_id'];
    }
    
        $replyPayload = [
            'id' => (int)$newReply['id'],
            'comment' => $newReply['comment'],
            'parent_id' => (int)$newReply['parent_id'],
            'created_at' => $newReply['created_at'],
            'updated_at' => $newReply['updated_at'],
            'reply_chain' => $replyChain,
            'author' => [
                'id' => (int)$newReply['user_id'],
                'username' => $newReply['username'],
                'profile_picture' => $newReply['profile_picture'] ? (strpos($newReply['profile_picture'], 'http') === 0 ? $newReply['profile_picture'] : '..' . $newReply['profile_picture']) : null,
                'is_pro' => (bool)$newReply['is_pro'],
                'comment_badge' => $newReply['comment_badge'],
                'name_badge' => $newReply['name_badge']
            ],
            'likes' => 0,
            'dislikes' => 0,
            'is_liked' => false,
            'is_disliked' => false,
            'is_creator' => ($newReply['user_id'] == $videoCreatorId),
            'can_edit' => ($newReply['user_id'] == $userId),
            'can_delete' => ($newReply['user_id'] == $userId || $userId == $videoCreatorId),
            'depth' => 0,
            'parent_author' => $parentAuthorName,
            'replies' => []
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Reply added successfully!',
            'reply' => $replyPayload
        ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add reply.',
        'error' => $e->getMessage()
    ]);
}
?>
