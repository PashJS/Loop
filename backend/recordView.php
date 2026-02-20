<?php
// backend/recordView.php - Record a video view for analytics
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_POST['video_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Video ID required']);
    exit;
}

$videoId = (int)$_POST['video_id'];

try {
    // Create view_history table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS view_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            INDEX idx_video_id (video_id),
            INDEX idx_viewed_at (viewed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Record view
    $stmt = $pdo->prepare("INSERT INTO view_history (video_id) VALUES (?)");
    $stmt->execute([$videoId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to record view']);
}
?>

