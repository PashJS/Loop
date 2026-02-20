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

if (!isset($data['content']) || empty(trim($data['content']))) {
    echo json_encode(['success' => false, 'message' => 'Post content cannot be empty']);
    exit;
}

$content = trim($data['content']);
$image_path = isset($data['image_path']) ? $data['image_path'] : null;

try {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image_path) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $content, $image_path]);
    
    echo json_encode(['success' => true, 'message' => 'Post created successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
