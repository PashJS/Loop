<?php
// backend/getWatchlists.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

$userId = $_SESSION['user_id'];

function formatVideo($v, $pdo) {
    $stmtAuthor = $pdo->prepare("SELECT id, username, profile_picture, is_pro FROM users WHERE id = ?");
    $stmtAuthor->execute([$v['user_id']]);
    $author = $stmtAuthor->fetch(PDO::FETCH_ASSOC);

    $urlFunc = function($url) {
        if (!$url) return '';
        if (strpos($url, 'http') === 0) return $url;
        if (strpos($url, '/') === 0) return '..' . $url;
        if (strpos($url, '..') === 0) return $url;
        return '../' . $url;
    };

    return [
        'id' => (int)$v['id'],
        'title' => $v['title'],
        'video_url' => $urlFunc($v['video_url']),
        'thumbnail_url' => $urlFunc($v['thumbnail_url']),
        'views' => (int)$v['views'],
        'likes_count' => (int)($v['likes_count'] ?? 0),
        'created_at' => $v['created_at'],
        'progress_seconds' => isset($v['progress_seconds']) ? (int)$v['progress_seconds'] : 0,
        'duration_seconds' => isset($v['duration_seconds']) ? (int)$v['duration_seconds'] : 0,
        'is_watched' => isset($v['is_watched']) ? (bool)$v['is_watched'] : false,
        'author' => [
            'id' => (int)$author['id'],
            'username' => $author['username'],
            'profile_picture' => $urlFunc($author['profile_picture']),
            'is_pro' => (bool)($author['is_pro'] ?? false)
        ]
    ];
}

// 1. Liked Videos
$likedQuery = "
    SELECT v.*, 
    (SELECT COUNT(*) FROM likes WHERE video_id = v.id AND type='like') as likes_count
    FROM videos v
    JOIN likes l ON v.id = l.video_id
    WHERE l.user_id = ? AND l.type = 'like'
    ORDER BY l.created_at DESC
";
$stmt = $pdo->prepare($likedQuery);
$stmt->execute([$userId]);
$likedVideos = array_map(function($v) use ($pdo) { return formatVideo($v, $pdo); }, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 2. Favorite Videos
$favoriteQuery = "
    SELECT v.*, 
    (SELECT COUNT(*) FROM likes WHERE video_id = v.id AND type='like') as likes_count
    FROM videos v
    JOIN favorites f ON v.id = f.video_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
";
$stmt = $pdo->prepare($favoriteQuery);
$stmt->execute([$userId]);
$favoriteVideos = array_map(function($v) use ($pdo) { return formatVideo($v, $pdo); }, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 3. Saved Videos
$saveQuery = "
    SELECT v.*, 
    (SELECT COUNT(*) FROM likes WHERE video_id = v.id AND type='like') as likes_count
    FROM videos v
    JOIN saves s ON v.id = s.video_id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
";
$stmt = $pdo->prepare($saveQuery);
$stmt->execute([$userId]);
$savedVideos = array_map(function($v) use ($pdo) { return formatVideo($v, $pdo); }, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 4. Progress
$progressQuery = "
    SELECT v.*, wp.progress_seconds, wp.duration_seconds, wp.is_watched, wp.updated_at as watch_date,
    (SELECT COUNT(*) FROM likes WHERE video_id = v.id AND type='like') as likes_count
    FROM videos v
    JOIN watch_progress wp ON v.id = wp.video_id
    WHERE wp.user_id = ?
    ORDER BY wp.updated_at DESC
";
$stmt = $pdo->prepare($progressQuery);
$stmt->execute([$userId]);
$allProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);

$watchedVideos = [];
$partiallyWatched = [];
foreach ($allProgress as $v) {
    if ($v['is_watched']) {
        $watchedVideos[] = formatVideo($v, $pdo);
    } else {
        $partiallyWatched[] = formatVideo($v, $pdo);
    }
}

echo json_encode([
    'success' => true,
    'lists' => [
        'liked' => $likedVideos,
        'favorites' => $favoriteVideos,
        'saved' => $savedVideos,
        'watched' => $watchedVideos,
        'partial' => $partiallyWatched
    ]
]);
?>
