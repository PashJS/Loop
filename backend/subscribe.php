<?php
// backend/subscribe.php - Subscribe/Unsubscribe to a channel
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to subscribe.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$channelId = isset($input['channel_id']) ? (int)$input['channel_id'] : 0;
$subscriberId = $_SESSION['user_id'];

if ($channelId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid channel ID.'
    ]);
    exit;
}

if ($channelId === $subscriberId) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot subscribe to yourself.'
    ]);
    exit;
}

try {
    // Create subscriptions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscriber_id INT NOT NULL,
            channel_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (channel_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_subscription (subscriber_id, channel_id),
            INDEX idx_subscriber (subscriber_id),
            INDEX idx_channel (channel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Check if subscription exists
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    $stmt->execute([$subscriberId, $channelId]);
    $subscription = $stmt->fetch();
    
    if ($subscription) {
        // Unsubscribe
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
        $stmt->execute([$subscriberId, $channelId]);
        $action = 'unsubscribed';
        $isSubscribed = false;
    } else {
        // Subscribe
        $stmt = $pdo->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)");
        $stmt->execute([$subscriberId, $channelId]);
        $action = 'subscribed';
        $isSubscribed = true;
        
        // Create notification for channel owner
        require 'createNotification.php';
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$subscriberId]);
        $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
        $message = $subscriber['username'] . " subscribed to your channel";
        createNotification($pdo, $channelId, 'subscription', $subscriberId, null, null, $message);
    }
    
    // Get updated subscriber count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE channel_id = ?");
    $stmt->execute([$channelId]);
    $subscriberCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'is_subscribed' => $isSubscribed,
        'subscriber_count' => (int)$subscriberCount
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update subscription.',
        'error' => $e->getMessage()
    ]);
}
?>
