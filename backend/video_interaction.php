<?php
// backend/video_interaction.php - Handle video likes/dislikes
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to interact with videos']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$videoId = $data['video_id'] ?? null;
$type = $data['type'] ?? null; // 'like' or 'dislike'

if (!$videoId || !$type) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Check if user already has a reaction
    $stmt = $pdo->prepare("SELECT id, type FROM likes WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['type'] === $type) {
            // Remove the reaction (unlike/undislike)
            $deleteStmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
            $deleteStmt->execute([$existing['id']]);
            
            echo json_encode([
                'success' => true,
                'action' => 'removed',
                'type' => $type
            ]);
        } else {
            // Update to the new reaction type
            $updateStmt = $pdo->prepare("UPDATE likes SET type = ? WHERE id = ?");
            $updateStmt->execute([$type, $existing['id']]);
            
            echo json_encode([
                'success' => true,
                'action' => 'updated',
                'type' => $type
            ]);
        }
    } else {
        // Add new reaction
        $insertStmt = $pdo->prepare("INSERT INTO likes (user_id, video_id, type) VALUES (?, ?, ?)");
        $insertStmt->execute([$userId, $videoId, $type]);
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'type' => $type
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
