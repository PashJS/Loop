<?php
// backend/getRecommendedVideos.php - Get personalized video recommendations based on interests cookie
header('Content-Type: application/json');
session_start();
require 'config.php';

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 24;
    $limit = min($limit, 100);
    $limit = max(1, $limit);

    $interests = [];
    if (isset($_COOKIE['flixwatch_interests'])) {
        $interestsData = json_decode($_COOKIE['flixwatch_interests'], true);
        if (is_array($interestsData)) {
            $interests = $interestsData;
        }
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $excludeId = isset($_GET['exclude']) ? (int)$_GET['exclude'] : 0;

    // Direct Hashtag Boosting for current video
    $currentVideoTags = [];
    if ($excludeId > 0) {
        try {
            $tagStmt = $pdo->prepare("
                SELECT h.tag_name 
                FROM hashtags h
                INNER JOIN video_hashtags vh ON h.id = vh.hashtag_id
                WHERE vh.video_id = ?
            ");
            $tagStmt->execute([$excludeId]);
            $currentVideoTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) { /* ignore */ }
    }

    if (empty($interests) && empty($currentVideoTags)) {
        // Fallback to recent videos if no interests found
        $sql = "
            SELECT v.id, v.title, v.description, v.video_url, v.thumbnail_url, v.views, v.created_at, v.is_clip, u.username, u.id as user_id, u.is_pro, u.profile_picture, ps.comment_badge, ps.name_badge,
            0 as relevance
            FROM videos v
            INNER JOIN users u ON v.user_id = u.id
            LEFT JOIN pro_settings ps ON u.id = ps.user_id
            WHERE v.status = 'published' AND v.id != ? ";
        
        if ($userId > 0) {
            $sql .= " AND v.id NOT IN (SELECT video_id FROM watch_progress WHERE user_id = ? AND is_watched = 1) AND v.user_id != ? ";
            $params = [$excludeId, $userId, $userId];
        } else {
            $params = [$excludeId];
        }
        $sql .= " AND (v.is_clip = 0 OR v.is_clip IS NULL) ";

        $sql .= " ORDER BY v.created_at DESC LIMIT " . (int)$limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Build recommendation query
        $hashtagInterests = [];
        $keywordInterests = [];
        
        // Take top 15 interests to avoid overly complex SQL
        $topInterests = array_slice($interests, 0, 15);
        
        foreach ($topInterests as $item) {
            if ($item['type'] === 'hashtag') {
                $hashtagInterests[] = $item;
            } else {
                $keywordInterests[] = $item;
            }
        }

        // We'll use a scoring system in SQL
        $scoreParts = [];
        $params = [':exclude_id' => $excludeId];

        // 0. Score based on hashtags of the CURRENT video (Highest priority)
        if (!empty($currentVideoTags)) {
            foreach ($currentVideoTags as $i => $tag) {
                $paramName = "cur_tag_" . $i;
                $scoreParts[] = "(SELECT COUNT(*) * 50 FROM video_hashtags vh INNER JOIN hashtags h ON vh.hashtag_id = h.id WHERE vh.video_id = v.id AND h.tag_name = :$paramName)";
                $params[$paramName] = $tag;
            }
        }

        // 1. Score based on historical hashtag interests
        if (!empty($hashtagInterests)) {
            foreach ($hashtagInterests as $i => $interest) {
                $paramName = "tag_" . $i;
                $weight = min(10, (int)($interest['score'] ?? 1));
                // Check if video has this hashtag
                $scoreParts[] = "(SELECT COUNT(*) * $weight FROM video_hashtags vh INNER JOIN hashtags h ON vh.hashtag_id = h.id WHERE vh.video_id = v.id AND h.tag_name = :$paramName)";
                $params[$paramName] = $interest['value'];
            }
        }

        // 2. Score based on title/description keywords
        if (!empty($keywordInterests)) {
            foreach ($keywordInterests as $i => $interest) {
                $paramName = "word_" . $i;
                $weight = min(10, (int)($interest['score'] ?? 1));
                
                // Match in title (higher weight)
                $scoreParts[] = "(CASE WHEN v.title LIKE :$paramName THEN $weight ELSE 0 END)";
                $params[$paramName] = '%' . $interest['value'] . '%';
                
                // Match in description (lower weight)
                $paramNameDesc = "word_desc_" . $i;
                $scoreParts[] = "(CASE WHEN v.description LIKE :$paramNameDesc THEN " . floor($weight / 2) . " ELSE 0 END)";
                $params[$paramNameDesc] = '%' . $interest['value'] . '%';
            }
        }

        $relevanceScore = !empty($scoreParts) ? implode(" + ", $scoreParts) : "0";
        
        // Final Query
        $sql = "
            SELECT 
                v.id, v.title, v.description, v.video_url, v.thumbnail_url, v.views, v.created_at, v.is_clip,
                u.username, u.id as user_id, u.is_pro, u.profile_picture, ps.comment_badge, ps.name_badge,
                ($relevanceScore) as relevance
            FROM videos v
            INNER JOIN users u ON v.user_id = u.id
            LEFT JOIN pro_settings ps ON u.id = ps.user_id
            WHERE v.status = 'published' AND v.id != :exclude_id ";

        if ($userId > 0) {
            $sql .= " AND v.id NOT IN (SELECT video_id FROM watch_progress WHERE user_id = :user_id AND is_watched = 1) AND v.user_id != :user_id_own ";
            $params[':user_id'] = $userId;
            $params[':user_id_own'] = $userId;
        }

        $sql .= " AND (v.is_clip = 0 OR v.is_clip IS NULL) ";

        $sql .= " ORDER BY relevance DESC, v.created_at DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fallback if no results
        if (count($videos) === 0) {
            $fallbackSql = "SELECT v.*, u.username, u.id as user_id, u.is_pro, u.profile_picture, ps.comment_badge, ps.name_badge, 0 as relevance 
                          FROM videos v 
                          INNER JOIN users u ON v.user_id = u.id 
                          LEFT JOIN pro_settings ps ON u.id = ps.user_id
                          WHERE v.status = 'published' AND v.id != :exclude_id ";
            $fParams = [':exclude_id' => $excludeId];
            if ($userId > 0) {
                $fallbackSql .= " AND v.id NOT IN (SELECT video_id FROM watch_progress WHERE user_id = :user_id AND is_watched = 1) AND v.user_id != :user_id_own ";
                $fParams[':user_id'] = $userId;
                $fParams[':user_id_own'] = $userId;
            }
            $fallbackSql .= " AND (v.is_clip = 0 OR v.is_clip IS NULL) ";
            $fallbackSql .= " ORDER BY v.created_at DESC LIMIT 24";
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute($fParams);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Format the response
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
            'is_clip' => (int)($video['is_clip'] ?? 0),
            'relevance' => isset($video['relevance']) ? (int)$video['relevance'] : 0,
            'author' => [
                'id' => (int)$video['user_id'],
                'username' => $video['username'],
                'profile_picture' => (function($url) {
                    if (!$url) return null;
                    if (strpos($url, 'http') === 0) return $url;
                    if (strpos($url, '/') === 0) return '..' . $url;
                    if (strpos($url, '..') === 0) return $url;
                    return '../' . $url;
                })($video['profile_picture']),
                'is_pro' => (bool)$video['is_pro'],
                'comment_badge' => $video['comment_badge'],
                'name_badge' => $video['name_badge']
            ]
        ];
    }, $videos);

    echo json_encode([
        'success' => true,
        'videos' => $formattedVideos,
        'count' => count($formattedVideos),
        'personalized' => !empty($interests)
    ]);

} catch (PDOException $e) {
    error_log("Recommendation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch recommendations.',
        'error' => $e->getMessage()
    ]);
}
