<?php
// backend/getUserProfile.php - Get user profile by username or ID
header('Content-Type: application/json');
session_start();
require 'config.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($userId <= 0 && empty($username)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'User ID or username is required.'
    ]);
    exit;
}

try {
    // Column availability check
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasBio = in_array('bio', $columns);
    $hasVisibility = in_array('profile_visibility', $columns);

    if (!$hasVisibility) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_visibility VARCHAR(20) DEFAULT 'public'");
            $hasVisibility = true;
        } catch (Exception $e) {}
    }

    $selectCols = "u.id, u.username, u.email, u.created_at, u.profile_picture, u.banner_url, u.is_pro, ps.comment_badge, ps.name_badge";
    $selectCols .= $hasBio ? ", u.bio" : ", NULL as bio";
    $selectCols .= $hasVisibility ? ", u.profile_visibility" : ", 'public' as profile_visibility";

    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT $selectCols FROM users u LEFT JOIN pro_settings ps ON u.id = ps.user_id WHERE u.id = ?");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT $selectCols FROM users u LEFT JOIN pro_settings ps ON u.id = ps.user_id WHERE u.username = ?");
        $stmt->execute([$username]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    
    
    // Get video count
    $stmt = $pdo->prepare("SELECT COUNT(*) as video_count FROM videos WHERE user_id = ? AND status = 'published'");
    $stmt->execute([$user['id']]);
    $videoCount = $stmt->fetch(PDO::FETCH_ASSOC)['video_count'];
    
    // Get subscriber count
    $subscriberCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as subscriber_count FROM subscriptions WHERE channel_id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $subscriberCount = (int)$row['subscriber_count'];
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    // Get following count
    $followingCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as following_count FROM subscriptions WHERE subscriber_id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $followingCount = (int)$row['following_count'];
    } catch (Exception $e) {
        // Table might not exist yet
    }

    
    // Check if current user is subscribed (if logged in)
    $isSubscribed = false;
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
            $stmt->execute([$_SESSION['user_id'], $user['id']]);
            $isSubscribed = $stmt->fetch() !== false;
        } catch (Exception $e) {}
    }
    
    $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id'];
    
    // Privacy check
    $isPrivate = false;
    if ($user['profile_visibility'] === 'private' && !$isOwner) {
        $isPrivate = true;
    } elseif ($user['profile_visibility'] === 'subscribers' && !$isOwner && !$isSubscribed) {
        $isPrivate = true;
    }
    
    // Get user's videos
    $formattedVideos = [];
    if (!$isPrivate) {
        $stmt = $pdo->prepare("
            SELECT 
                v.id,
                v.title,
                v.description,
                v.video_url,
                v.thumbnail_url,
                v.views,
                v.created_at,
                (SELECT COUNT(*) FROM likes WHERE video_id = v.id) as likes_count
            FROM videos v
            WHERE v.user_id = ? AND v.status = 'published'
            ORDER BY v.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user['id']]);
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
                'likes' => (int)$video['likes_count'],
                'created_at' => $video['created_at']
            ];
        }, $videos);
    }
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $isOwner ? $user['email'] : null,
            'created_at' => $user['created_at'],
            'profile_picture' => (function($url) {
                if (!$url) return null;
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($user['profile_picture']),
            'bio' => $isPrivate ? '' : ($user['bio'] ?? ''),
            'video_count' => (int)$videoCount,
            'subscriber_count' => (int)$subscriberCount,
            'following_count' => (int)$followingCount,
            'is_subscribed' => $isSubscribed,
            'is_private' => $isPrivate,
            'is_owner' => $isOwner,
            'is_pro' => (bool)$user['is_pro'],
            'comment_badge' => $user['comment_badge'],
            'name_badge' => $user['name_badge'],
            'banner_url' => (function($url) {
                if (!$url) return null;
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($user['banner_url'])
        ],
        'videos' => $formattedVideos
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Get user profile error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch user profile.',
        'error' => $e->getMessage()
    ]);
}
?>

