<?php
// backend/getSubscribers.php - Get list of subscribers for a channel
header('Content-Type: application/json');
session_start();
require 'config.php';

$channelId = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0;

if ($channelId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Channel ID is required.'
    ]);
    exit;
}

try {
    // Get subscribers
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.profile_picture,
            s.created_at as subscribed_at
        FROM subscriptions s
        INNER JOIN users u ON s.subscriber_id = u.id
        WHERE s.channel_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$channelId]);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSubscribers = array_map(function($sub) {
        return [
            'id' => (int)$sub['id'],
            'username' => $sub['username'],
            'profile_picture' => $sub['profile_picture'] ? (strpos($sub['profile_picture'], 'http') === 0 ? $sub['profile_picture'] : '..' . $sub['profile_picture']) : null,
            'subscribed_at' => $sub['subscribed_at']
        ];
    }, $subscribers);
    
    echo json_encode([
        'success' => true,
        'subscribers' => $formattedSubscribers,
        'count' => count($formattedSubscribers)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch subscribers.',
        'error' => $e->getMessage()
    ]);
}
?>
