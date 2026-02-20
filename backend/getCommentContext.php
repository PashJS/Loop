<?php
// backend/getCommentContext.php - Get a specific comment and its replies for the notification panel
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_GET['comment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment ID is required.']);
    exit;
}

$commentId = (int)$_GET['comment_id'];
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    // 1. Find the target comment and its root (if it's a reply)
    $stmt = $pdo->prepare("SELECT parent_id, id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $curr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$curr) {
        echo json_encode(['success' => false, 'message' => 'Comment not found.']);
        exit;
    }

    $rootId = $curr['parent_id'] ?? $curr['id'];

    // 2. Fetch the root comment and all its direct replies
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            u.username, u.profile_picture, u.is_pro,
            ps.comment_badge, ps.name_badge
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE c.id = ? OR c.parent_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$rootId, $rootId]);
    $allInThread = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get likes/dislikes/reactions for all IDs in thread
    $threadIds = array_column($allInThread, 'id');
    $likes = [];
    $dislikes = [];
    $userReactions = [];
    $reactionCounts = [];

    if (!empty($threadIds)) {
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        
        // Likes
        $stmt = $pdo->prepare("SELECT comment_id, COUNT(*) as cnt FROM comment_likes WHERE comment_id IN ($placeholders) GROUP BY comment_id");
        $stmt->execute($threadIds);
        foreach ($stmt->fetchAll() as $r) { $likes[(int)$r['comment_id']] = (int)$r['cnt']; }

        // Dislikes
        $stmt = $pdo->prepare("SELECT comment_id, COUNT(*) as cnt FROM comment_dislikes WHERE comment_id IN ($placeholders) GROUP BY comment_id");
        $stmt->execute($threadIds);
        foreach ($stmt->fetchAll() as $r) { $dislikes[(int)$r['comment_id']] = (int)$r['cnt']; }

        if ($userId) {
            // User reactions
            $params = array_merge($threadIds, [$userId]);
            $stmt = $pdo->prepare("SELECT comment_id, emoji FROM comment_reactions WHERE comment_id IN ($placeholders) AND user_id = ?");
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) { $userReactions[(int)$r['comment_id']] = $r['emoji']; }
        }

        // Reaction counts
        $stmt = $pdo->prepare("SELECT comment_id, emoji, COUNT(*) as cnt FROM comment_reactions WHERE comment_id IN ($placeholders) GROUP BY comment_id, emoji");
        $stmt->execute($threadIds);
        foreach ($stmt->fetchAll() as $r) {
            $reactionsById[(int)$r['comment_id']][] = [
                'emoji' => $r['emoji'],
                'count' => (int)$r['cnt']
            ];
        }
    }

    // 4. Format thread
    $formattedThread = [];
    $root = null;

    foreach ($allInThread as $c) {
        $id = (int)$c['id'];
        $formatted = [
            'id' => $id,
            'comment' => $c['comment'],
            'parent_id' => $c['parent_id'],
            'created_at' => $c['created_at'],
            'likes' => $likes[$id] ?? 0,
            'dislikes' => $dislikes[$id] ?? 0,
            'user_reaction' => $userReactions[$id] ?? null,
            'reactions' => $reactionsById[$id] ?? [],
            'author' => [
                'id' => (int)$c['user_id'],
                'username' => $c['username'],
                'profile_picture' => $c['profile_picture'] ? (strpos($c['profile_picture'], 'http') === 0 ? $c['profile_picture'] : '..' . $c['profile_picture']) : null,
                'is_pro' => (bool)$c['is_pro'],
                'comment_badge' => $c['comment_badge'],
                'name_badge' => $c['name_badge']
            ],
            'replies' => []
        ];

        if ($id == $rootId) {
            $root = $formatted;
        } else {
            $formattedThread[] = $formatted;
        }
    }

    if ($root) {
        $root['replies'] = $formattedThread;
        $root['highlight_id'] = ($commentId != $rootId) ? $commentId : null;
    }

    echo json_encode([
        'success' => true,
        'comment' => $root
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
