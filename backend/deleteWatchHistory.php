<?php
// backend/deleteWatchHistory.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$videoId = isset($input['id']) ? (int)$input['id'] : 0;
$userId = $_SESSION['user_id'];

if ($videoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM watch_progress WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
