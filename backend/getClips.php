<?php
// backend/getClips.php - Fetch video clips
header('Content-Type: application/json');
session_start();
require 'config.php';

try {
    $interests = [];
    if (isset($_COOKIE['flixwatch_interests'])) {
        $interestsData = json_decode($_COOKIE['flixwatch_interests'], true);
        if (is_array($interestsData)) {
            $interests = array_slice($interestsData, 0, 15);
        }
    }

    $params = [];
    $relevanceScore = "0";

    if (!empty($interests)) {
        $scoreParts = [];
        foreach ($interests as $i => $interest) {
            $weight = min(10, (int)($interest['score'] ?? 1));
            if ($interest['type'] === 'hashtag') {
                $paramName = "tag_" . $i;
                $scoreParts[] = "(SELECT COUNT(*) * $weight FROM video_hashtags vh INNER JOIN hashtags h ON vh.hashtag_id = h.id WHERE vh.video_id = v.id AND h.tag_name = :$paramName)";
                $params[$paramName] = $interest['value'];
            } else {
                $paramName = "word_" . $i;
                $scoreParts[] = "(CASE WHEN v.title LIKE :$paramName THEN $weight ELSE 0 END)";
                $params[$paramName] = '%' . $interest['value'] . '%';
                
                $paramNameDesc = "word_desc_" . $i;
                $scoreParts[] = "(CASE WHEN v.description LIKE :$paramNameDesc THEN " . floor($weight / 2) . " ELSE 0 END)";
                $params[$paramNameDesc] = '%' . $interest['value'] . '%';
            }
        }
        if (!empty($scoreParts)) {
            $relevanceScore = implode(" + ", $scoreParts);
        }
    }

    // Fetch clips (videos with is_clip = 1)
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $currentSessionId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    $whereClause = "v.is_clip = 1 AND v.status = 'published'";
    if ($userId > 0) {
        $whereClause .= " AND v.user_id = :clip_user_id";
        $params['clip_user_id'] = $userId;
    } else if ($currentSessionId > 0) {
        // Exclude own clips when browsing generally (like on Home)
        $whereClause .= " AND v.user_id != :exclude_own_id";
        $params['exclude_own_id'] = $currentSessionId;
    }

    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.title,
            v.description,
            v.video_url,
            v.thumbnail_url,
            v.views,
            v.created_at,
            u.id as user_id,
            u.username,
            u.profile_picture,
            u.is_pro,
            ps.comment_badge,
            ps.name_badge,
            ($relevanceScore) as relevance
        FROM videos v
        JOIN users u ON v.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE $whereClause
        ORDER BY relevance DESC, v.created_at DESC
        LIMIT 30
    ");
    
    $stmt->execute($params);
    $clips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NEW: Auto-generate missing thumbnails (max 1 per request for speed)
    require_once 'thumbnail_helper.php';
    foreach ($clips as &$v) {
        if (empty($v['thumbnail_url'])) {
             $newThumb = generateRandomThumbnail($v['id'], $v['video_url'], $pdo);
             if ($newThumb) {
                 $v['thumbnail_url'] = $newThumb;
                 break;
             }
        }
    }
    unset($v);
    
    // Add engagement data for each clip
    foreach ($clips as &$clip) {
        // Get likes count (handle if table doesn't exist)
        try {
            $likesStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE video_id = ? AND type = 'like'");
            $likesStmt->execute([$clip['id']]);
            $clip['likes'] = (int)$likesStmt->fetchColumn();
            
            $dislikesStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE video_id = ? AND type = 'dislike'");
            $dislikesStmt->execute([$clip['id']]);
            $clip['dislikes'] = (int)$dislikesStmt->fetchColumn();
        } catch (PDOException $e) {
            $clip['likes'] = 0;
            $clip['dislikes'] = 0;
        }
        
        // Get comments count
        try {
            $commentsStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
            $commentsStmt->execute([$clip['id']]);
            $clip['comments_count'] = (int)$commentsStmt->fetchColumn();
        } catch (PDOException $e) {
            $clip['comments_count'] = 0;
        }
        
        // Check if current user liked/disliked
        $clip['user_liked'] = false;
        $clip['user_disliked'] = false;
        $clip['is_subscribed'] = false;
        
        if (isset($_SESSION['user_id'])) {
            try {
                $stmtLike = $pdo->prepare("SELECT type FROM likes WHERE video_id = ? AND user_id = ?");
                $stmtLike->execute([$clip['id'], $_SESSION['user_id']]);
                $userAction = $stmtLike->fetchColumn();
                
                $clip['user_liked'] = $userAction === 'like';
                $clip['user_disliked'] = $userAction === 'dislike';
            } catch (PDOException $e) {
                // Likes table doesn't exist, skip
            }
            
            // Check subscription status
            try {
                $stmtSub = $pdo->prepare("SELECT 1 FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
                $stmtSub->execute([$_SESSION['user_id'], $clip['user_id']]);
                $clip['is_subscribed'] = (bool)$stmtSub->fetchColumn();
            } catch (PDOException $e) {
                // Subscriptions table doesn't exist, skip
            }
        }
    }
    
    // Format the response to ensure robust paths
    $formattedClips = array_map(function($clip) {
        return [
            'id' => (int)$clip['id'],
            'title' => $clip['title'],
            'description' => $clip['description'],
            'video_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($clip['video_url']),
            'thumbnail_url' => (function($url) {
                if (!$url) return '';
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($clip['thumbnail_url']),
            'views' => (int)$clip['views'],
            'created_at' => $clip['created_at'],
            'likes' => (int)($clip['likes'] ?? 0),
            'dislikes' => (int)($clip['dislikes'] ?? 0),
            'comments_count' => (int)($clip['comments_count'] ?? 0),
            'user_liked' => (bool)$clip['user_liked'],
            'user_disliked' => (bool)$clip['user_disliked'],
            'is_subscribed' => (bool)$clip['is_subscribed'],
            'relevance' => (int)$clip['relevance'],
            'username' => $clip['username'],
            'is_pro' => (bool)$clip['is_pro'],
            'comment_badge' => $clip['comment_badge'],
            'name_badge' => $clip['name_badge'],
            'profile_picture' => (function($url) {
                if (!$url) return null;
                if (strpos($url, 'http') === 0) return $url;
                if (strpos($url, '/') === 0) return '..' . $url;
                if (strpos($url, '..') === 0) return $url;
                return '../' . $url;
            })($clip['profile_picture']),
            'author' => [
                'id' => (int)$clip['user_id'],
                'username' => $clip['username'],
                'is_pro' => (bool)$clip['is_pro'],
                'comment_badge' => $clip['comment_badge'],
                'name_badge' => $clip['name_badge'],
                'profile_picture' => (function($url) {
                    if (!$url) return null;
                    if (strpos($url, 'http') === 0) return $url;
                    if (strpos($url, '/') === 0) return '..' . $url;
                    if (strpos($url, '..') === 0) return $url;
                    return '../' . $url;
                })($clip['profile_picture'])
            ]
        ];
    }, $clips);
    
    echo json_encode([
        'success' => true,
        'clips' => $formattedClips,
        'count' => count($formattedClips)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
