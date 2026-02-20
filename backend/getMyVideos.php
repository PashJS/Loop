<?php
// backend/getMyVideos.php - Get current user's videos
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

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.title,
            v.description,
            v.video_url,
            v.thumbnail_url,
            v.status,
            v.views,
            v.created_at,
            v.is_clip,
            (SELECT COUNT(*) FROM likes WHERE video_id = v.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE video_id = v.id) as comments_count,
            (SELECT COUNT(*) FROM favorites WHERE video_id = v.id) as hearts_count,
            (SELECT COUNT(*) FROM saves WHERE video_id = v.id) as saves_count
        FROM videos v
        WHERE v.user_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$userId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedVideos = array_map(function($video) {
        return [
            'id' => (int)$video['id'],
            'title' => $video['title'],
            'description' => $video['description'],
            'video_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($video['video_url']),
            'thumbnail_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($video['thumbnail_url']),
            'status' => $video['status'],
            'is_clip' => (bool)$video['is_clip'],
            'views' => (int)$video['views'],
            'likes' => (int)$video['likes_count'],
            'comments' => (int)$video['comments_count'],
            'hearts' => (int)$video['hearts_count'],
            'saves' => (int)$video['saves_count'],
            'created_at' => $video['created_at']
        ];
    }, $videos);
    
    echo json_encode([
        'success' => true,
        'videos' => $formattedVideos,
        'count' => count($formattedVideos)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch videos.',
        'error' => $e->getMessage()
    ]);
}
?>
