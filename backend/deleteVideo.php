<?php
// backend/deleteVideo.php - Delete a video
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
    // Verify video belongs to user
    $stmt = $pdo->prepare("SELECT id, video_url, thumbnail_url FROM videos WHERE id = ? AND user_id = ?");
    $stmt->execute([$videoId, $userId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$video) {
        echo json_encode([
            'success' => false,
            'message' => 'Video not found or you do not have permission to delete it.'
        ]);
        exit;
    }
    
    // Delete video file
    if ($video['video_url'] && file_exists(__DIR__ . '/..' . $video['video_url'])) {
        unlink(__DIR__ . '/..' . $video['video_url']);
    }
    
    // Delete thumbnail file
    if ($video['thumbnail_url'] && file_exists(__DIR__ . '/..' . $video['thumbnail_url'])) {
        unlink(__DIR__ . '/..' . $video['thumbnail_url']);
    }
    
    // Delete video from database (cascade will handle related records)
    $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Video deleted successfully!'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete video.',
        'error' => $e->getMessage()
    ]);
}
?>

