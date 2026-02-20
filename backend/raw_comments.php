<?php
// backend/raw_comments.php - Admin debug: dump comments rows for a video
header('Content-Type: application/json');
require 'config.php';

try {
    if (!isset($_GET['video_id'])) {
        echo json_encode(['success' => false, 'message' => 'video_id required']);
        http_response_code(400);
        exit;
    }
    $videoId = (int)$_GET['video_id'];

    $stmt = $pdo->prepare("SELECT * FROM comments WHERE video_id = ? ORDER BY created_at ASC");
    $stmt->execute([$videoId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'count' => count($rows), 'rows' => $rows]);

    // Log action
    $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/replies.log', sprintf("%s\tRAW_COMMENTS\tvideo=%s\tcount=%d\n", date('c'), $videoId, count($rows)), FILE_APPEND | LOCK_EX);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
