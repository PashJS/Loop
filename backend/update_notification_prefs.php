<?php
// backend/update_notification_prefs.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$allowed_keys = ['email_notifications', 'subscription_notifications', 'notification_frequency', 'profile_visibility'];
$updates = [];
$params = [];

foreach ($input as $key => $value) {
    if (in_array($key, $allowed_keys)) {
        $updates[] = "$key = ?";
        if ($key === 'notification_frequency' || $key === 'profile_visibility') {
            $params[] = (string)$value;
        } else {
            $params[] = $value ? 1 : 0;
        }
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'Nothing to update']);
    exit;
}

try {
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $user_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Notification preferences updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
