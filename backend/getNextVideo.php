<?php
// backend/getNextVideo.php - Get the next video ID for autoplay
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require 'config.php';

$currentId = isset($_GET['current_id']) ? (int)$_GET['current_id'] : 0;
$direction = isset($_GET['direction']) ? $_GET['direction'] : 'next';

try {
    if ($direction === 'prev') {
        // Find previous video with lower ID
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE id < ? AND status = 'published' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$currentId]);
        $nextVideo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no previous video, wrap to last video
        if (!$nextVideo) {
            $stmt = $pdo->prepare("SELECT id FROM videos WHERE status = 'published' ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $nextVideo = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // Try to find the next video with a higher ID
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE id > ? AND status = 'published' ORDER BY id ASC LIMIT 1");
        $stmt->execute([$currentId]);
        $nextVideo = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($nextVideo) {
        // Fetch additional info for UI
        $stmtInfo = $pdo->prepare("SELECT title, thumbnail_url, video_url FROM videos WHERE id = ? AND status = 'published'");
        $stmtInfo->execute([$nextVideo['id']]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        // Process paths
        $thumbnailUrl = $info['thumbnail_url'] ?? '';
        if ($thumbnailUrl && strpos($thumbnailUrl, 'http') !== 0) {
            $thumbnailUrl = '../' . ltrim($thumbnailUrl, './');
        }
        
        $videoUrl = $info['video_url'] ?? '';
        if ($videoUrl && strpos($videoUrl, 'http') !== 0) {
            $videoUrl = '../' . ltrim($videoUrl, './');
        }

        echo json_encode([
            'success' => true,
            'video_id' => $nextVideo['id'],
            'title' => $info['title'] ?? '',
            'thumbnail_url' => $thumbnailUrl,
            'video_url' => $videoUrl
        ]);
        exit;
    }

    // If no next video (end of list), wrap around to the first video
    $stmt = $pdo->prepare("SELECT id FROM videos WHERE status = 'published' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $firstVideo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($firstVideo && $firstVideo['id'] != $currentId) {
        // Fetch additional info for UI
        $stmtInfo = $pdo->prepare("SELECT title, thumbnail_url, video_url FROM videos WHERE id = ? AND status = 'published'");
        $stmtInfo->execute([$firstVideo['id']]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        // Process paths
        $thumbnailUrl = $info['thumbnail_url'] ?? '';
        if ($thumbnailUrl && strpos($thumbnailUrl, 'http') !== 0) {
            $thumbnailUrl = '../' . ltrim($thumbnailUrl, './');
        }
        
        $videoUrl = $info['video_url'] ?? '';
        if ($videoUrl && strpos($videoUrl, 'http') !== 0) {
            $videoUrl = '../' . ltrim($videoUrl, './');
        }

        echo json_encode([
            'success' => true,
            'video_id' => $firstVideo['id'],
            'title' => $info['title'] ?? '',
            'thumbnail_url' => $thumbnailUrl,
            'video_url' => $videoUrl
        ]);
        exit;
    }


    // If we are the only video or no videos exist
    echo json_encode(['success' => false, 'message' => 'No other videos found']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
