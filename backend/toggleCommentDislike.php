<?php
// backend/toggleCommentDislike.php - Dislike/Undislike comment
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to dislike comments.'
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
    // Check if dislike exists
    $stmt = $pdo->prepare("SELECT id FROM comment_dislikes WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$userId, $commentId]);
    $dislike = $stmt->fetch();
    
    if ($dislike) {
        // Undislike: Delete the dislike
        $stmt = $pdo->prepare("DELETE FROM comment_dislikes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        $action = 'undisliked';
    } else {
        // Dislike: Insert the dislike and remove like if exists
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        
        $stmt = $pdo->prepare("INSERT INTO comment_dislikes (user_id, comment_id) VALUES (?, ?)");
        $stmt->execute([$userId, $commentId]);
        $action = 'disliked';
    }
    
    // Get updated dislike count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_dislikes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $dislikeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'dislikes' => (int)$dislikeCount,
        'likes' => (int)$likeCount,
        'is_disliked' => $action === 'disliked'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to toggle comment dislike.',
        'error' => $e->getMessage()
    ]);
}
?>
