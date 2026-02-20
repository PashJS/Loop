<?php
// backend/getVideosByUser.php - Fetch videos by a specific user
header('Content-Type: application/json');
session_start();
require 'config.php';

try {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $excludeId = isset($_GET['exclude']) ? (int)$_GET['exclude'] : 0;

    if ($userId === 0) {
        throw new Exception("User ID is required.");
    }

    $sql = "
        SELECT 
            v.id,
            v.title,
            v.description,
            v.video_url,
            v.thumbnail_url,
            v.views,
            v.user_id,
            v.created_at,
            u.username,
            u.profile_picture
        FROM videos v
        INNER JOIN users u ON v.user_id = u.id
        WHERE v.user_id = ? 
        AND v.status = 'published' 
        AND v.id != ?
        AND (v.is_clip = 0 OR v.is_clip IS NULL)
        ORDER BY v.created_at DESC 
        LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $excludeId]);
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
            'views' => (int)$video['views'],
            'created_at' => $video['created_at'],
            'author' => [
                'id' => (int)$video['user_id'],
                'username' => $video['username'],
                'profile_picture' => (function($url) {
                    if (!$url) return null;
                    if (strpos($url, 'http') === 0) return $url;
                    if (strpos($url, '/') === 0) return '..' . $url;
                    if (strpos($url, '..') === 0) return $url;
                    return '../' . $url;
                })($video['profile_picture'])
            ]
        ];
    }, $videos);

    echo json_encode([
        'success' => true,
        'videos' => $formattedVideos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
