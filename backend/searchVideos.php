<?php
ob_start();
session_start();
require 'config.php';
ob_clean(); // Clear any previous output (whitespace, warnings)
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

try {
    // Check which columns exist (video_url vs video_filename)
    $stmt = $pdo->query("SHOW COLUMNS FROM videos LIKE 'video_url'");
    $hasVideoUrl = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM videos LIKE 'thumbnail_url'");
    $hasThumbnailUrl = $stmt->rowCount() > 0;
    
    // Build SELECT clause based on available columns
    $videoUrlCol = $hasVideoUrl ? 'v.video_url' : "CONCAT('/uploads/videos/', v.video_filename) as video_url";
    $thumbnailUrlCol = $hasThumbnailUrl ? 'v.thumbnail_url' : "CONCAT('/uploads/thumbnails/', v.thumbnail_filename) as thumbnail_url";
    
    $sql = "
        SELECT 
            v.id,
            v.title,
            v.description,
            " . ($hasVideoUrl ? 'v.video_url' : "CONCAT('/uploads/videos/', v.video_filename) as video_url") . ",
            " . ($hasThumbnailUrl ? 'v.thumbnail_url' : "CONCAT('/uploads/thumbnails/', v.thumbnail_filename) as thumbnail_url") . ",
            v.views,
            v.user_id,
            v.created_at,
            u.username,
            u.id as user_id,
            u.profile_picture
        FROM videos v
        INNER JOIN users u ON v.user_id = u.id
        WHERE (v.status = 1 OR v.status = 'published')
    ";

    $params = [];
    if (!empty($query)) {
        $sql .= " AND (v.title LIKE ? OR v.description LIKE ? OR u.username LIKE ?)";
        $searchTerm = '%' . $query . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    $sql .= " ORDER BY v.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $formattedVideos = array_map(function($video) {
        return [
            'id' => (int)$video['id'],
            'title' => $video['title'] ?? 'Untitled',
            'description' => $video['description'] ?? '',
            'video_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($video['video_url'] ?? ''),
            'thumbnail_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($video['thumbnail_url'] ?? ''),
            'views' => (int)($video['views'] ?? 0),
            'created_at' => $video['created_at'] ?? date('Y-m-d H:i:s'),
            'author' => [
                'id' => (int)$video['user_id'],
                'username' => $video['username'] ?? 'Unknown',
                'profile_picture' => (function($url) {
                    if (!$url) return null;
                    if (strpos($url, 'http') === 0) return $url;
                    if (strpos($url, '/') === 0) return '..' . $url;
                    if (strpos($url, '..') === 0) return $url;
                    return '../' . $url;
                })($video['profile_picture'] ?? null)
            ]
        ];
    }, $videos);

    echo json_encode([
        'success' => true,
        'videos' => $formattedVideos,
        'count' => count($formattedVideos)
    ], JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed',
        'error' => $e->getMessage()
    ]);
}
ob_end_flush();
?>
