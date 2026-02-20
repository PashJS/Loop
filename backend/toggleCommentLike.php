<?php
// backend/toggleCommentLike.php - Like/Unlike comment
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to like comments.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$commentId = isset($input['comment_id']) ? (int)$input['comment_id'] : 0;
$userId = $_SESSION['user_id'];

if ($commentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment ID.'
    ]);
    exit;
}

try {
    // Check if like exists
    $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$userId, $commentId]);
    $like = $stmt->fetch();
    
    if ($like) {
        // Unlike: Delete the like
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        $action = 'unliked';
    } else {
        // Like: Insert the like and remove dislike if exists
        $stmt = $pdo->prepare("DELETE FROM comment_dislikes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        
        $stmt = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
        $stmt->execute([$userId, $commentId]);
        $action = 'liked';
        
        // Create notification for comment author
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        $commentAuthorId = $comment['user_id'] ?? null;
        
        if ($commentAuthorId && $commentAuthorId != $userId) {
            require 'createNotification.php';
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $liker = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = $liker['username'] . " liked your comment";
            createNotification($pdo, $commentAuthorId, 'comment_like', $userId, $commentId, 'comment', $message);
        }
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get dislike count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_dislikes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $dislikeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'likes' => (int)$likeCount,
        'dislikes' => (int)$dislikeCount,
        'is_liked' => $action === 'liked'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to toggle comment like.',
        'error' => $e->getMessage()
    ]);
}
?>
