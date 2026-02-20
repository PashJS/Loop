<?php
// backend/toggleCommentReaction.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to react.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$commentId = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
$emoji = isset($data['emoji']) ? trim($data['emoji']) : '';
$userId = $_SESSION['user_id'];

if ($commentId <= 0 || empty($emoji)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    // Auto-migrate comment_reactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            emoji VARCHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment_reaction (user_id, comment_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Check if user already has a reaction (and handle duplicates via DELETE first)
    $stmt = $pdo->prepare("SELECT emoji FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hadSameEmoji = false;
    foreach ($existingRows as $row) {
        if ($row['emoji'] === $emoji) {
            $hadSameEmoji = true;
        }
    }

    // Always DELETE all reactions for this user/comment first to clean up old duplicates
    $stmt = $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    
    if ($hadSameEmoji) {
        // We just deleted it, so it's a toggle OFF.
        $action = 'removed';
    } else {
        // Insert new single reaction
        $stmt = $pdo->prepare("INSERT INTO comment_reactions (comment_id, user_id, emoji) VALUES (?, ?, ?)");
        $stmt->execute([$commentId, $userId, $emoji]);
        $action = 'added';
    }

    // Get updated counts for this comment
    $stmt = $pdo->prepare("
        SELECT emoji, COUNT(*) as count 
        FROM comment_reactions 
        WHERE comment_id = ? 
        GROUP BY emoji 
        ORDER BY count DESC
    ");
    $stmt->execute([$commentId]);
    $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'reactions' => $reactions
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
