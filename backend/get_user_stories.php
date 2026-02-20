<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Fetch stories from the last 24 hours
    $stmt = $pdo->prepare("SELECT stories.*, users.username, users.profile_picture 
                           FROM stories 
                           JOIN users ON stories.user_id = users.id 
                           WHERE stories.user_id = ? 
                            AND stories.created_at >= NOW() - INTERVAL 1 DAY 
                           ORDER BY stories.created_at ASC");
    $stmt->execute([$userId]);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'stories' => $stories]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
