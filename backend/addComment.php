<?php
// backend/addComment.php - Add a comment to a video
header('Content-Type: application/json');
session_start();
require 'config.php';

// Ensure we handle ALL errors as JSON
try {
    // 1. Input Parsing
    $rawInput = file_get_contents('php://input');
    $input = [];
    
    if (is_string($rawInput) && $rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    
    // Fallback to POST if input is empty
    if (empty($input)) {
        $input = $_POST ?? [];
    }

    // 2. Auth Check
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    } elseif (isset($input['user_id'])) {
        $userId = (int)$input['user_id'];
    } else {
        throw new Exception('You must be logged in to comment.', 401);
    }
    $_SESSION['user_id'] = $userId;

    // 3. Mute Check
    $muteCheck = $pdo->prepare("SELECT comment_banned_until FROM users WHERE id = ? AND comment_banned_until IS NOT NULL AND comment_banned_until > NOW()");
    $muteCheck->execute([$userId]);
    $muteResult = $muteCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($muteResult) {
        $muteExpires = date('M j, Y \a\t g:i A', strtotime($muteResult['comment_banned_until']));
        echo json_encode([
            'success' => false,
            'message' => "You are temporarily muted from commenting until $muteExpires."
        ]);
        exit;
    }

    // 4. Validate Input
    $videoId = isset($input['video_id']) ? (int)$input['video_id'] : 0;
    $comment = isset($input['comment']) ? trim($input['comment']) : '';

    if ($videoId <= 0) throw new Exception('Invalid video ID.');
    if (empty($comment)) throw new Exception('Comment cannot be empty.');
    if (strlen($comment) > 1000) throw new Exception('Comment is too long (max 1000 characters).');

    // 5. Moderation
    if (file_exists('moderateComment.php')) {
        require_once 'moderateComment.php';
        $moderation = moderateComment($comment);
        if ($moderation['blocked']) {
            echo json_encode([
                'success' => false,
                'message' => 'Your comment contains inappropriate language. Please revise your comment.'
            ]);
            exit;
        }
        $comment = $moderation['filtered'];
    }

    // 6. DB Operations
    // Verify video
    $stmt = $pdo->prepare("SELECT id, user_id FROM videos WHERE id = ? AND status = 'published'");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$video) throw new Exception('Video not found.');
    $videoOwnerId = $video['user_id'];

    // Insert
    $stmt = $pdo->prepare("INSERT INTO comments (user_id, video_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $videoId, $comment]);
    $commentId = $pdo->lastInsertId();

    // Notify (safely)
    try {
        if ($videoOwnerId && $videoOwnerId != $userId && file_exists('createNotification.php')) {
            require_once 'createNotification.php';
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $commenter = $stmt->fetch(PDO::FETCH_ASSOC);
            $username = $commenter['username'] ?? 'Someone';
            $message = "$username commented on your video";
            createNotification($pdo, $videoOwnerId, 'video_comment', $userId, $videoId, 'video', $message);
        }
    } catch (Exception $e) {
        // Find notification errors effectively ignored so comment still posts
        error_log("Notification Error: " . $e->getMessage());
    }

    // Fetch Result
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.comment, c.created_at, c.updated_at,
            u.id as user_id, u.username, u.profile_picture, u.is_pro,
            ps.comment_badge, ps.name_badge
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$commentId]);
    $newComment = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully!',
        'comment' => [
            'id' => (int)$newComment['id'],
            'comment' => $newComment['comment'],
            'parent_id' => null,
            'created_at' => $newComment['created_at'],
            'updated_at' => $newComment['updated_at'],
            'likes' => 0,
            'dislikes' => 0,
            'is_liked' => false,
            'is_disliked' => false,
            'is_pinned' => false,
            'is_creator' => ($newComment['user_id'] == $videoOwnerId),
            'can_edit' => ((int)$newComment['user_id'] === (int)$userId),
            'can_delete' => ((int)$newComment['user_id'] === (int)$userId) || ((int)$userId === (int)$videoOwnerId),
            'can_pin' => false,
            'replies' => [],
            'author' => [
                'id' => (int)$newComment['user_id'],
                'username' => $newComment['username'],
                'profile_picture' => $newComment['profile_picture'] ? (strpos($newComment['profile_picture'], 'http') === 0 ? $newComment['profile_picture'] : '..' . $newComment['profile_picture']) : null,
                'is_pro' => (bool)$newComment['is_pro'],
                'comment_badge' => $newComment['comment_badge'],
                'name_badge' => $newComment['name_badge']
            ]
        ]
    ]);

} catch (Throwable $e) {
    http_response_code($e->getCode() === 401 ? 401 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
