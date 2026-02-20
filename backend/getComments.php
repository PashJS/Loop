<?php
// backend/getComments.php - Get comments for a video
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_GET['video_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Video ID is required.'
    ]);
    exit;
}

$videoId = (int)$_GET['video_id'];
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    // Debug: log comments load attempts (helps track when frontend requests comments)
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logEntry = sprintf("%s\tGET_COMMENTS\tvideo=%s\tuser=%s\n", date('c'), $videoId, $userId ?? 'null');
    @file_put_contents($logDir . '/replies.log', $logEntry, FILE_APPEND | LOCK_EX);
    // Get video creator ID
    $stmt = $pdo->prepare("SELECT user_id FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $videoCreatorId = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'] ?? null;
    
    // Create pinned_comments table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pinned_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            video_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_pinned_comment (comment_id),
            INDEX idx_video_id (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Create comment_reactions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            emoji VARCHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment_reaction (user_id, comment_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create comment_likes table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment_like (user_id, comment_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Create comment_dislikes table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_dislikes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment_dislike (user_id, comment_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create pro_settings table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pro_settings (
            user_id INT PRIMARY KEY,
            comment_badge VARCHAR(50) DEFAULT 'pro',
            name_badge VARCHAR(10) DEFAULT 'on',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Get pinned comment ID
    $stmt = $pdo->prepare("SELECT comment_id FROM pinned_comments WHERE video_id = ?");
    $stmt->execute([$videoId]);
    $pinnedCommentId = $stmt->fetch(PDO::FETCH_ASSOC)['comment_id'] ?? null;
    if ($pinnedCommentId === null) $pinnedCommentId = 0; // Ensure it's an integer for comparison
    
    // Get top-level comments (no parent_id) - pinned first, then by date
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    // 1. Get Paginated Top-Level Comments
    $sqlTop = "
        SELECT 
            c.id, c.comment, c.parent_id, c.created_at, c.updated_at, c.user_id, 
            u.username, u.profile_picture, u.is_pro,
            ps.comment_badge, ps.name_badge
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE c.video_id = ? AND (c.parent_id IS NULL OR c.parent_id = 0)
        ORDER BY 
            CASE WHEN c.id = ? THEN 0 ELSE 1 END,
            c.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sqlTop);
    $stmt->execute([$videoId, $pinnedCommentId]);
    $topLevelComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get All Replies (For now, we fetch all replies to ensure checking nesting works. 
    // Optimization: In a huge app, we'd only fetch replies for the ids in $topLevelComments)
    // We only fetch replies if we actually have top level comments or if offset is 0? 
    // Actually, replies might appear even if top level is paginated? No, replies hang off top level.
    // If we only show 20 top level, we only need their replies.
    
    $topIds = array_column($topLevelComments, 'id');
    $replies = [];
    
    if (!empty($topIds)) {
        // Fetch replies that assume these top IDs are somewhere in their ancestry.
        // Simplification: Just fetch ALL replies for the video. 
        // Use the proper columns.
        $sqlReplies = "
            SELECT 
                c.id, c.comment, c.parent_id, c.created_at, c.updated_at, c.user_id, 
                u.username, u.profile_picture, u.is_pro,
                ps.comment_badge, ps.name_badge
            FROM comments c
            INNER JOIN users u ON c.user_id = u.id
            LEFT JOIN pro_settings ps ON u.id = ps.user_id
            WHERE c.video_id = ? AND (c.parent_id IS NOT NULL AND c.parent_id != 0)
            ORDER BY c.created_at ASC
        ";
        $stmt = $pdo->prepare($sqlReplies);
        $stmt->execute([$videoId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Merge
    $allComments = array_merge($topLevelComments, $replies);

    // Build an indexed map of comments by id for quick lookups
    $byId = [];
    foreach ($allComments as $c) {
        $id = (int)$c['id'];
        $byId[$id] = [
            'id' => $id,
            'comment' => $c['comment'],
            'parent_id' => $c['parent_id'] !== null ? (int)$c['parent_id'] : null,
            'created_at' => $c['created_at'],
            'updated_at' => $c['updated_at'],
            'user_id' => (int)$c['user_id'],
            'username' => $c['username'],
            'profile_picture' => $c['profile_picture'] ? (strpos($c['profile_picture'], 'http') === 0 ? $c['profile_picture'] : '../' . ltrim($c['profile_picture'], './')) : null,
            'is_pro' => (bool)$c['is_pro'],
            'comment_badge' => $c['comment_badge'],
            'name_badge' => $c['name_badge'],
            'replies' => []
        ];
    }

    // Attach children to parents to form the tree
    $topLevel = [];
    $attachedReplyIds = [];
    
    foreach ($byId as $id => &$node) {
        $pid = $node['parent_id'];
        if ($pid === null) {
            $topLevel[$id] = &$node;
        } else {
            if (isset($byId[$pid])) {
                $byId[$pid]['replies'][] = &$node;
                $attachedReplyIds[] = $id;
            } else {
                // parent missing — keep as orphan (will promote later)
            }
        }
    }
    unset($node); // Break reference

    // Identify orphans (replies whose parents are missing from the fetched set)
    // In this logic, any node with a parent_id that wasn't attached is an orphan.
    // We can iterate $byId and check if it has a parent_id but isn't in $attachedReplyIds
    $orphans = [];
    foreach ($byId as $id => $node) {
        if ($node['parent_id'] !== null && !in_array($id, $attachedReplyIds)) {
            $orphans[] = $node;
        }
    }

    // Get reaction counts
    $allIds = array_keys($byId);
    $likesById = [];
    $dislikesById = [];
    
    if (!empty($allIds)) {
        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        
        $stmt = $pdo->prepare("SELECT comment_id, COUNT(*) as cnt FROM comment_likes WHERE comment_id IN ($placeholders) GROUP BY comment_id");
        $stmt->execute($allIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $likesById[(int)$r['comment_id']] = (int)$r['cnt'];
        }

        $stmt = $pdo->prepare("SELECT comment_id, COUNT(*) as cnt FROM comment_dislikes WHERE comment_id IN ($placeholders) GROUP BY comment_id");
        $stmt->execute($allIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dislikesById[(int)$r['comment_id']] = (int)$r['cnt'];
        }
    }

    // Get user-specific reaction sets
    $userLikes = [];
    $userDislikes = [];
    if ($userId && !empty($allIds)) {
        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $stmt = $pdo->prepare("SELECT comment_id FROM comment_likes WHERE comment_id IN ($placeholders) AND user_id = ?");
        $params = array_merge($allIds, [$userId]);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $userLikes[(int)$r['comment_id']] = true; }

        $stmt = $pdo->prepare("SELECT comment_id FROM comment_dislikes WHERE comment_id IN ($placeholders) AND user_id = ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $userDislikes[(int)$r['comment_id']] = true; }
    }

    // Get emoji reactions
    $reactionsById = [];
    $userReactionsById = [];
    if (!empty($allIds)) {
        $stmt = $pdo->prepare("
            SELECT comment_id, emoji, COUNT(*) as cnt 
            FROM comment_reactions 
            WHERE comment_id IN ($placeholders) 
            GROUP BY comment_id, emoji
            ORDER BY cnt DESC
        ");
        $stmt->execute($allIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $reactionsById[(int)$r['comment_id']][] = [
                'emoji' => $r['emoji'],
                'count' => (int)$r['cnt']
            ];
        }

        if ($userId) {
            $stmt = $pdo->prepare("SELECT comment_id, emoji FROM comment_reactions WHERE comment_id IN ($placeholders) AND user_id = ?");
            $params = array_merge($allIds, [$userId]);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $userReactionsById[(int)$r['comment_id']] = $r['emoji'];
            }
        }
    }

    // Recursive formatter
    $formatNode = function($node) use (&$formatNode, $likesById, $dislikesById, $userLikes, $userDislikes, $reactionsById, $userReactionsById, $videoCreatorId, $userId, $byId, $pinnedCommentId) {
        $id = (int)$node['id'];
        $likes = $likesById[$id] ?? 0;
        $dislikes = $dislikesById[$id] ?? 0;
        $isLiked = !empty($userLikes[$id]);
        $isDisliked = !empty($userDislikes[$id]);
        $reactions = $reactionsById[$id] ?? [];
        $userReaction = $userReactionsById[$id] ?? null;
        
        // Resolve parent author name if this is a reply
        $parentAuthor = null;
        if ($node['parent_id'] !== null && isset($byId[$node['parent_id']])) {
            $parentAuthor = $byId[$node['parent_id']]['username'];
        }

        $formatted = [
            'id' => $id,
            'comment' => $node['comment'],
            'parent_id' => $node['parent_id'],
            'parent_author' => $parentAuthor, // Add parent author name for UI
            'created_at' => $node['created_at'],
            'updated_at' => $node['updated_at'],
            'likes' => $likes,
            'dislikes' => $dislikes,
            'is_liked' => $isLiked,
            'is_disliked' => $isDisliked,
            'reactions' => $reactions,
            'user_reaction' => $userReaction,
            'is_pinned' => ($id == $pinnedCommentId),
            'is_creator' => ($node['user_id'] == $videoCreatorId),
            'can_edit' => ($node['user_id'] == $userId),
            'can_delete' => ($node['user_id'] == $userId || $userId == $videoCreatorId),
            'can_pin' => ($userId == $videoCreatorId),
            'replies' => [],
            'author' => [
                'id' => (int)$node['user_id'],
                'username' => $node['username'],
                'profile_picture' => $node['profile_picture'],
                'is_pro' => $node['is_pro'],
                'comment_badge' => $node['comment_badge'],
                'name_badge' => $node['name_badge']
            ]
        ];

        foreach ($node['replies'] as $child) {
            $formatted['replies'][] = $formatNode($child);
        }

        return $formatted;
    };

    $formattedComments = [];
    foreach ($topLevel as $node) {
        $formattedComments[] = $formatNode($node);
    }
    
    // Process orphans - promote them to top level but mark them or just show them
    // For now, we just add them to the list so they aren't lost.
    foreach ($orphans as $orphan) {
        $formattedOrphan = $formatNode($orphan);
        $formattedOrphan['missing_parent'] = true;
        // Try to find parent author even if parent isn't in the tree (maybe deleted?)
        // Since we fetched ALL comments for the video, if parent isn't in $byId, it's likely deleted or doesn't exist.
        $formattedComments[] = $formattedOrphan;
    }
    
    $response = [
        'success' => true,
        'comments' => $formattedComments,
        'count' => count($formattedComments)
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch comments.',
        'error' => $e->getMessage()
    ]);
}
