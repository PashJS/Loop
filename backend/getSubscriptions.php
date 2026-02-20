<?php
// backend/getSubscriptions.php - Get list of channels a user is subscribed to
header('Content-Type: application/json');
session_start();
require 'config.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required.'
    ]);
    exit;
}

try {
    // Get subscriptions
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.profile_picture,
            s.created_at as subscribed_at,
            (SELECT COUNT(*) FROM subscriptions WHERE channel_id = u.id) as subscriber_count,
            (SELECT COUNT(*) FROM videos WHERE user_id = u.id AND status = 'published') as video_count
        FROM subscriptions s
        INNER JOIN users u ON s.channel_id = u.id
        WHERE s.subscriber_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSubscriptions = array_map(function($sub) {
        return [
            'id' => (int)$sub['id'],
            'username' => $sub['username'],
            'profile_picture' => $sub['profile_picture'] ? (strpos($sub['profile_picture'], 'http') === 0 ? $sub['profile_picture'] : '..' . $sub['profile_picture']) : null,
            'subscribed_at' => $sub['subscribed_at'],
            'subscriber_count' => (int)$sub['subscriber_count'],
            'video_count' => (int)$sub['video_count']
        ];
    }, $subscriptions);
    
    echo json_encode([
        'success' => true,
        'subscriptions' => $formattedSubscriptions,
        'count' => count($formattedSubscriptions)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch subscriptions.',
        'error' => $e->getMessage()
    ]);
}
?>
