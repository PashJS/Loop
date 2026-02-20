<?php
// backend/getVideos.php - Get video recommendations
ob_start();
header('Content-Type: application/json');
session_start();
require 'config.php';
ob_clean(); // Clear any previous output

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = min($limit, 50); // Max 50 videos
    $limit = max(1, $limit); // Min 1 video
    
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
    // Build query with filters
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
            u.id as user_id,
            u.profile_picture
        FROM videos v
        INNER JOIN users u ON v.user_id = u.id
        WHERE v.status = 'published' 
        AND (v.is_clip = 0 OR v.is_clip IS NULL) ";

    $params = [];
    if ($userId > 0) {
        $sql .= " AND v.user_id != ? ";
        $params[] = $userId;
    }

    $sql .= " ORDER BY v.created_at DESC LIMIT " . (int)$limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // NEW: Background thumbnail generation for first missing one found
    require_once 'thumbnail_helper.php';
    foreach ($videos as &$v) {
        if (empty($v['thumbnail_url'])) {
             $newThumb = generateRandomThumbnail($v['id'], $v['video_url'], $pdo);
             if ($newThumb) {
                 $v['thumbnail_url'] = $newThumb;
                 break; // Only do one per request to avoid slow loads
             }
        }
    }
    unset($v);

    // Format the response
    $formattedVideos = array_map(function($video) {
        return [
            'id' => (int)$video['id'],
            'title' => $video['title'],
            'description' => $video['description'],
            'video_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                return ltrim($url, '/');
            })($video['video_url']),
            'thumbnail_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                return ltrim($url, '/');
            })($video['thumbnail_url']),
            'views' => (int)$video['views'],
            'created_at' => $video['created_at'],
            'author' => [
                'id' => (int)$video['user_id'],
                'username' => $video['username'],
                'profile_picture' => (function($url) {
                    if (!$url) return null;
                    if (strpos($url, 'http') === 0) return $url;
                    return ltrim($url, '/');
                })($video['profile_picture'])
            ]
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
