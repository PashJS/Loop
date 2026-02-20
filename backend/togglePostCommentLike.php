<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['comment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing comment ID']);
    exit;
}

$comment_id = (int)$data['comment_id'];

try {
    // Check if like exists
    $stmt = $pdo->prepare("SELECT id FROM post_comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$user_id, $comment_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Unlike
        $pdo->prepare("DELETE FROM post_comment_likes WHERE id = ?")->execute([$existing['id']]);
        $status = 'unliked';
    } else {
        // Like and remove dislike
        $pdo->prepare("DELETE FROM post_comment_dislikes WHERE user_id = ? AND comment_id = ?")->execute([$user_id, $comment_id]);
        $pdo->prepare("INSERT INTO post_comment_likes (user_id, comment_id) VALUES (?, ?)")->execute([$user_id, $comment_id]);
        $status = 'liked';
    }

    // Get updated counts
    $likes = $pdo->prepare("SELECT COUNT(*) FROM post_comment_likes WHERE comment_id = ?");
    $likes->execute([$comment_id]);
    $likesCount = $likes->fetchColumn();

    $dislikes = $pdo->prepare("SELECT COUNT(*) FROM post_comment_dislikes WHERE comment_id = ?");
    $dislikes->execute([$comment_id]);
    $dislikesCount = $dislikes->fetchColumn();

    $isLiked = $pdo->prepare("SELECT COUNT(*) FROM post_comment_likes WHERE user_id = ? AND comment_id = ?");
    $isLiked->execute([$user_id, $comment_id]);
    $userLiked = $isLiked->fetchColumn() > 0;

    $isDisliked = $pdo->prepare("SELECT COUNT(*) FROM post_comment_dislikes WHERE user_id = ? AND comment_id = ?");
    $isDisliked->execute([$user_id, $comment_id]);
    $userDisliked = $isDisliked->fetchColumn() > 0;

    echo json_encode([
        'success' => true, 
        'likes' => $likesCount, 
        'dislikes' => $dislikesCount,
        'is_liked' => $userLiked,
        'is_disliked' => $userDisliked,
        'status' => $status
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
