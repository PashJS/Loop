<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['comment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$id = (int)$data['comment_id'];

try {
    // Check ownership or if post owner (optional, let's stick to commenter for now)
    $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ?");
    $stmt->execute([$id]);
    $comment = $stmt->fetch();

    if (!$comment || (int)$comment['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Not allowed']);
        exit;
    }

    $pdo->prepare("DELETE FROM post_comments WHERE id = ?")->execute([$id]);
    
    // Decrement post comment count
    $pdo->prepare("UPDATE posts SET comments = comments - 1 WHERE id = ?")->execute([$comment['post_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
