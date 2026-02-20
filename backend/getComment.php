<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/getNestedReplies.php';

header('Content-Type: application/json');

try {
    $pdo = getDb();

    // Accept either comment_id or id param
    $commentId = isset($_GET['comment_id']) ? (int)$_GET['comment_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Missing comment_id']);
        http_response_code(400);
        exit;
    }

    // Simple auth check to get current user if available
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    $stmt = $pdo->prepare("SELECT c.*, u.username, u.profile_picture FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$commentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        http_response_code(404);
        exit;
    }

    // Fetch raw replies for this comment (we'll use getNestedReplies helper to construct nested structure)
    $stmt = $pdo->prepare("SELECT c.*, u.username, u.profile_picture FROM comments c INNER JOIN users u ON c.user_id = u.id WHERE c.video_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at ASC");
    $stmt->execute([$row['video_id']]);
    $allForVideo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build nested replies for this comment
    $replies = getNestedReplies((int)$commentId, $allForVideo, $pdo, $userId, /*videoCreatorId*/ null, 0);

    // Likes/dislikes
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comment_likes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $likes = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comment_dislikes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $dislikes = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $isLiked = false; $isDisliked = false;
    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        $isLiked = $stmt->fetch() !== false;

        $stmt = $pdo->prepare("SELECT id FROM comment_dislikes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        $isDisliked = $stmt->fetch() !== false;
    }

    $formatted = [
        'id' => (int)$row['id'],
        'comment' => $row['comment'],
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'likes' => $likes,
        'dislikes' => $dislikes,
        'is_liked' => $isLiked,
        'is_disliked' => $isDisliked,
        'replies' => $replies,
        'author' => [
            'id' => (int)$row['user_id'],
            'username' => $row['username'],
            'profile_picture' => $row['profile_picture'] ? (strpos($row['profile_picture'], 'http') === 0 ? $row['profile_picture'] : '..' . $row['profile_picture']) : null
        ]
    ];

    echo json_encode(['success' => true, 'comment' => $formatted]);

    // Logging
    $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/replies.log', sprintf("%s\tGET_COMMENT\tid=%s\n", date('c'), $commentId), FILE_APPEND | LOCK_EX);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}

?>
