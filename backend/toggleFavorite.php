<?php
// backend/toggleFavorite.php - Favorite/Unfavorite video
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to favorite videos.'
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
    // Check if favorite exists
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $favorite = $stmt->fetch();
    
    if ($favorite) {
        // Unfavorite: Delete the favorite
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $action = 'unfavorited';
    } else {
        // Favorite: Insert the favorite
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, video_id) VALUES (?, ?)");
        $stmt->execute([$userId, $videoId]);
        $action = 'favorited';
    }
    
    // Get updated favorite count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM favorites WHERE video_id = ?");
    $stmt->execute([$videoId]);
    $favoriteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'favorites' => (int)$favoriteCount,
        'is_favorited' => $action === 'favorited'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to toggle favorite.',
        'error' => $e->getMessage()
    ]);
}
?>
