<?php
// backend/toggleDislike.php - Dislike/Undislike video
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to dislike videos.'
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
    // Check if dislike exists
    $stmt = $pdo->prepare("SELECT id FROM dislikes WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $dislike = $stmt->fetch();
    
    if ($dislike) {
        // Undislike: Delete the dislike
        $stmt = $pdo->prepare("DELETE FROM dislikes WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $action = 'undisliked';
    } else {
        // Dislike: Insert the dislike and remove like if exists
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        
        $stmt = $pdo->prepare("INSERT INTO dislikes (user_id, video_id) VALUES (?, ?)");
        $stmt->execute([$userId, $videoId]);
        $action = 'disliked';
    }
    
    // Get updated dislike count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dislikes WHERE video_id = ?");
    $stmt->execute([$videoId]);
    $dislikeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE video_id = ?");
    $stmt->execute([$videoId]);
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
        'message' => 'Failed to toggle dislike.',
        'error' => $e->getMessage()
    ]);
}
?>
