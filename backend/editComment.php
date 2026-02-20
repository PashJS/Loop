<?php
// backend/editComment.php - Edit a comment
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$commentId = isset($input['comment_id']) ? (int)$input['comment_id'] : 0;
$newComment = isset($input['comment']) ? trim($input['comment']) : '';
$userId = $_SESSION['user_id'];

if ($commentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment ID.'
    ]);
    exit;
}

if (empty($newComment)) {
    echo json_encode([
        'success' => false,
        'message' => 'Comment cannot be empty.'
    ]);
    exit;
}

if (strlen($newComment) > 1000) {
    echo json_encode([
        'success' => false,
        'message' => 'Comment is too long (max 1000 characters).'
    ]);
    exit;
}

// Moderate comment
require 'moderateComment.php';
$moderation = moderateComment($newComment);
if ($moderation['blocked']) {
    echo json_encode([
        'success' => false,
        'message' => 'Your comment contains inappropriate language. Please revise your comment.'
    ]);
    exit;
}
$newComment = $moderation['filtered'];

try {
    // Verify user owns the comment
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found.'
        ]);
        exit;
    }
    
    if ($comment['user_id'] != $userId) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to edit this comment.'
        ]);
        exit;
    }
    
    // Update comment
    $stmt = $pdo->prepare("UPDATE comments SET comment = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$newComment, $commentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment updated successfully!',
        'comment' => $newComment
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update comment.',
        'error' => $e->getMessage()
    ]);
}
?>

