<?php
// backend/getFavoritedVideos.php
header('Content-Type: application/json');
session_start();
require 'config.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0 && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
}

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            v.id, v.title, v.description, v.video_url, v.thumbnail_url, v.views, v.created_at,
            u.username, u.profile_picture,
            (SELECT COUNT(*) FROM likes WHERE video_id = v.id) as likes_count
        FROM favorites f
        JOIN videos v ON f.video_id = v.id
        JOIN users u ON v.user_id = u.id
        WHERE f.user_id = ?
        ORDER BY f.id DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format URLs
    $formattedVideos = array_map(function($video) {
        $fixUrl = function($url) {
            if (empty($url)) return '';
            if (strpos($url, 'http') === 0) return $url;
            if (strpos($url, '..') === 0) return $url;
            return '../' . ltrim($url, '/');
        };

        $video['video_url'] = $fixUrl($video['video_url']);
        $video['thumbnail_url'] = $fixUrl($video['thumbnail_url']);
        $video['profile_picture'] = $fixUrl($video['profile_picture']);
        return $video;
    }, $videos);

    echo json_encode(['success' => true, 'videos' => $formattedVideos]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>
