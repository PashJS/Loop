<?php
// backend/updateWatchProgress.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$videoId = (int)($input['video_id'] ?? 0);
$progress = (float)($input['progress'] ?? 0);
$duration = (float)($input['duration'] ?? 0);
$userId = $_SESSION['user_id'];

if ($videoId <= 0 || $duration <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

// Consider watched if > 90%
$isWatched = ($progress / $duration) > 0.9 ? 1 : 0;

try {
    // Auto-migrate watch_progress
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS watch_progress (
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            progress_seconds DECIMAL(10,2) NOT NULL,
            duration_seconds DECIMAL(10,2) NOT NULL,
            is_watched TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, video_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO watch_progress (user_id, video_id, progress_seconds, duration_seconds, is_watched)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            progress_seconds = VALUES(progress_seconds),
            duration_seconds = VALUES(duration_seconds),
            is_watched = VALUES(is_watched),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$userId, $videoId, $progress, $duration, $isWatched]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
