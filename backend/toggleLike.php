<?php
// backend/toggleLike.php - Like/Unlike video
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to like videos.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$videoId = isset($input['video_id']) ? (int)$input['video_id'] : 0;
$userId = $_SESSION['user_id'];

if ($videoId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid video ID.'
    ]);
    exit;
}

try {
    // Check if like exists
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $like = $stmt->fetch();
    
    if ($like) {
        // Unlike: Delete the like
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $action = 'unliked';
    } else {
        // Like: Insert the like and remove dislike if exists
        $stmt = $pdo->prepare("DELETE FROM dislikes WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, video_id) VALUES (?, ?)");
        $stmt->execute([$userId, $videoId]);
        $action = 'liked';
        
        // Create notification for video owner
        $stmt = $pdo->prepare("SELECT user_id FROM videos WHERE id = ?");
        $stmt->execute([$videoId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        $videoOwnerId = $video['user_id'] ?? null;
        
        if ($videoOwnerId && $videoOwnerId != $userId) {
            require 'createNotification.php';
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $liker = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = $liker['username'] . " liked your video";
            createNotification($pdo, $videoOwnerId, 'video_like', $userId, $videoId, 'video', $message);
        }
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE video_id = ?");
    $stmt->execute([$videoId]);
    $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get dislike count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dislikes WHERE video_id = ?");
    $stmt->execute([$videoId]);
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
        'message' => 'Failed to toggle like.',
        'error' => $e->getMessage()
    ]);
}
?>