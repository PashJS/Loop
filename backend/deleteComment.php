<?php
// backend/deleteComment.php - Delete a comment
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
$userId = $_SESSION['user_id'];

if ($commentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment ID.'
    ]);
    exit;
}

try {
    // Check if user owns the comment or is the video creator
    $stmt = $pdo->prepare("
        SELECT c.user_id, c.video_id, v.user_id as video_owner_id
        FROM comments c
        LEFT JOIN videos v ON c.video_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found.'
        ]);
        exit;
    }
    
    // Check permissions
    $canDelete = ($comment['user_id'] == $userId) || ($comment['video_owner_id'] == $userId);
    
    if (!$canDelete) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to delete this comment.'
        ]);
        exit;
    }
    
    // Delete comment (cascade will handle replies)
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted successfully!'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete comment.',
        'error' => $e->getMessage()
    ]);
}
?>

