<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['story'])) {
    $userId = $_SESSION['user_id'];
    $type = $_POST['type'] ?? 'image';
    $file = $_FILES['story'];
    
    $extension = ($type === 'video') ? 'mp4' : 'jpg';
    $fileName = uniqid('story_') . '.' . $extension;
    $uploadDir = '../uploads/stories/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO stories (user_id, type, content_path) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $type, $fileName]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
