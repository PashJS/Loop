<?php
// backend/getVideoById.php - Get video by ID
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);
session_start();
require 'config.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Video ID is required.'
    ]);
    exit;
}

$videoId = (int)$_GET['id'];
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    // Get video with user info
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.title,
            v.description,
            v.video_url,
            v.thumbnail_url,
            v.captions_url,
            v.views,
            v.user_id,
            v.created_at,
            u.username,
            u.id as author_id,
            u.is_pro,
            ps.comment_badge,
            ps.name_badge
        FROM videos v
        INNER JOIN users u ON v.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE v.id = ? AND v.status = 'published'
    ");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$video) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Video not found.'
        ]);
        exit;
    }

    // NEW: Auto-generate thumbnail ONLY if missing
    if (empty($video['thumbnail_url'])) {
        require_once 'thumbnail_helper.php';
        $newThumb = generateRandomThumbnail($video['id'], $video['video_url'], $pdo);
        if ($newThumb) $video['thumbnail_url'] = $newThumb;
    }
    
    // Increment views
    $stmt = $pdo->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
    $stmt->execute([$videoId]);
    
    // Get like count
    $likeCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE video_id = ?");
        $stmt->execute([$videoId]);
        $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting likes: " . $e->getMessage());
    }
    
    // Get dislike count
    $dislikeCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dislikes WHERE video_id = ?");
        $stmt->execute([$videoId]);
        $dislikeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting dislikes: " . $e->getMessage());
    }
    
    // Get favorite count (table might not exist)
    $favoriteCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM favorites WHERE video_id = ?");
        $stmt->execute([$videoId]);
        $favoriteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting favorites (table might not exist): " . $e->getMessage());
        // Table doesn't exist, that's okay
    }
    
    // Get subscriber count for the author
    $subscriberCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE channel_id = ?");
        $stmt->execute([$video['author_id']]);
        $subscriberCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting subscribers: " . $e->getMessage());
    }
    
    // Check if current user liked/disliked/favorited this video
    $isLiked = false;
    $isDisliked = false;
    $isFavorited = false;
    $isSaved = false;
    if ($userId) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND video_id = ?");
            $stmt->execute([$userId, $videoId]);
            $isLiked = $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking if liked: " . $e->getMessage());
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM dislikes WHERE user_id = ? AND video_id = ?");
            $stmt->execute([$userId, $videoId]);
            $isDisliked = $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking if disliked: " . $e->getMessage());
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND video_id = ?");
            $stmt->execute([$userId, $videoId]);
            $isFavorited = $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking if favorited: " . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM saves WHERE user_id = ? AND video_id = ?");
            $stmt->execute([$userId, $videoId]);
            $isSaved = $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking if saved: " . $e->getMessage());
        }
    }
    
    // Get hashtags
    $hashtags = [];
    try {
        $stmt = $pdo->prepare("
            SELECT h.tag_name 
            FROM hashtags h
            INNER JOIN video_hashtags vh ON h.id = vh.hashtag_id
            WHERE vh.video_id = ?
        ");
        $stmt->execute([$videoId]);
        $hashtags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting hashtags: " . $e->getMessage());
        // Table might not exist yet, ignore
    }
    // Get chapters
    $chapters = [];
    try {
        $stmt = $pdo->prepare("
            SELECT title, start_time, end_time 
            FROM video_chapters 
            WHERE video_id = ?
            ORDER BY start_time ASC
        ");
        $stmt->execute([$videoId]);
        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting chapters: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'video' => [
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
            'captions_url' => (function($url) {
                if (!$url) return null;
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($video['captions_url'] ?? null),
            'views' => (int)$video['views'] + 1, // Include the increment
            'created_at' => $video['created_at'],
            'hashtags' => $hashtags,
            'chapters' => $chapters,
            'likes' => (int)$likeCount,
            'dislikes' => (int)$dislikeCount,
            'favorites' => (int)$favoriteCount,
            'is_liked' => $isLiked,
            'is_disliked' => $isDisliked,
            'is_favorited' => $isFavorited,
            'is_saved' => $isSaved,
            'author' => [
                'id' => (int)$video['author_id'],
                'username' => $video['username'],
                'subscriber_count' => (int)$subscriberCount,
                'is_pro' => (bool)$video['is_pro'],
                'comment_badge' => $video['comment_badge'],
                'name_badge' => $video['name_badge']
            ]
        ]
    ]);
} catch (PDOException $e) {
    error_log("Fatal error in getVideoById.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch video.',
        'error' => $e->getMessage()
    ]);
}
