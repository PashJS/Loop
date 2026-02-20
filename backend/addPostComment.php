<?php
ob_start();
header('Content-Type: application/json');
session_start();
require_once 'config.php';
ob_clean();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['post_id']) || !isset($data['comment']) || empty(trim($data['comment']))) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$post_id = (int)$data['post_id'];
$comment = trim($data['comment']);
$parent_id = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

try {
    $stmt = $pdo->prepare("INSERT INTO post_comments (user_id, post_id, comment, parent_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $post_id, $comment, $parent_id]);
    
    // Increment comment count in posts table
    $pdo->prepare("UPDATE posts SET comments = comments + 1 WHERE id = ?")->execute([$post_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Comment added',
        'id' => (int)$pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
