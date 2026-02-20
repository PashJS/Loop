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

if (!isset($data['post_id'])) {
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit;
}

$post_id = (int)$data['post_id'];

try {
    // Check if already liked
    $check = $pdo->prepare("SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    $check->execute([$user_id, $post_id]);
    
    if ($check->fetch()) {
        // Unlike
        $pdo->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?")->execute([$user_id, $post_id]);
        $pdo->prepare("UPDATE posts SET likes = GREATEST(0, likes - 1) WHERE id = ?")->execute([$post_id]);
        $status = 'unliked';
    } else {
        // Like
        $pdo->prepare("INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)")->execute([$user_id, $post_id]);
        $pdo->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?")->execute([$post_id]);
        $status = 'liked';
    }
    
    // Get new count
    $stmt = $pdo->prepare("SELECT likes FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $newCount = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'status' => $status, 'likes' => $newCount]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
