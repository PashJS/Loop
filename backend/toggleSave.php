<?php
// backend/toggleSave.php - Bookmark/Unsave video
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
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
    echo json_encode(['success' => false, 'message' => 'Invalid video ID.']);
    exit;
}

try {
    // Check if save exists
    $stmt = $pdo->prepare("SELECT id FROM saves WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $save = $stmt->fetch();
    
    if ($save) {
        $stmt = $pdo->prepare("DELETE FROM saves WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $action = 'unsaved';
    } else {
        $stmt = $pdo->prepare("INSERT INTO saves (user_id, video_id) VALUES (?, ?)");
        $stmt->execute([$userId, $videoId]);
        $action = 'saved';
    }
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'is_saved' => $action === 'saved'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
