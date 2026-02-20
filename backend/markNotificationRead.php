<?php
// backend/markNotificationRead.php - Mark notification as read
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
$markAll = isset($input['mark_all']) && $input['mark_all'] === true;
$userId = $_SESSION['user_id'];

try {
    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } else if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request.'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update notification.',
        'error' => $e->getMessage()
    ]);
}
?>



