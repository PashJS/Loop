<?php
// backend/pinComment.php - Pin/unpin a comment (video creator only)
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
    // Check if user is video creator
    $stmt = $pdo->prepare("
        SELECT v.user_id as video_owner_id, c.video_id
        FROM comments c
        INNER JOIN videos v ON c.video_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$commentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found.'
        ]);
        exit;
    }
    
    if ($result['video_owner_id'] != $userId) {
        echo json_encode([
            'success' => false,
            'message' => 'Only the video creator can pin comments.'
        ]);
        exit;
    }
    
    // Create pinned_comments table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pinned_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            video_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_pinned_comment (comment_id),
            INDEX idx_video_id (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Check if already pinned
    $stmt = $pdo->prepare("SELECT id FROM pinned_comments WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $pinned = $stmt->fetch();
    
    if ($pinned) {
        // Unpin
        $stmt = $pdo->prepare("DELETE FROM pinned_comments WHERE comment_id = ?");
        $stmt->execute([$commentId]);
        echo json_encode([
            'success' => true,
            'message' => 'Comment unpinned successfully!',
            'pinned' => false
        ]);
    } else {
        // Unpin any existing pinned comment for this video
        $stmt = $pdo->prepare("DELETE FROM pinned_comments WHERE video_id = ?");
        $stmt->execute([$result['video_id']]);
        
        // Pin this comment
        $stmt = $pdo->prepare("INSERT INTO pinned_comments (comment_id, video_id) VALUES (?, ?)");
        $stmt->execute([$commentId, $result['video_id']]);
        echo json_encode([
            'success' => true,
            'message' => 'Comment pinned successfully!',
            'pinned' => true
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to pin/unpin comment.',
        'error' => $e->getMessage()
    ]);
}
?>

