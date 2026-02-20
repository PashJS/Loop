<?php
// backend/hideNotification.php - Handle notification hiding/blocking
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$action = $data['action'] ?? '';
$notifId = isset($data['notif_id']) ? (int)$data['notif_id'] : 0;

try {
    // Migrate: Add is_hidden column to notifications if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN is_hidden TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    // Migrate: Create notification_preferences table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            value VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pref (user_id, type, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($action === 'hide_this' && $notifId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_hidden = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
    } 
    elseif ($action === 'hide_user' && $notifId > 0) {
        // Get actor_id
        $stmt = $pdo->prepare("SELECT actor_id FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        $actorId = $stmt->fetchColumn();
        
        if ($actorId) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO notification_preferences (user_id, type, value) VALUES (?, 'hidden_user', ?)");
            $stmt->execute([$userId, $actorId]);
            // Also hide all existing ones? or just mark this one? 
            // User usually expects all from this user to disappear or at least future ones.
        }
    }
    elseif ($action === 'hide_type' && $notifId > 0) {
        // Get type
        $stmt = $pdo->prepare("SELECT type FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        $type = $stmt->fetchColumn();
        
        if ($type) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO notification_preferences (user_id, type, value) VALUES (?, 'hidden_type', ?)");
            $stmt->execute([$userId, $type]);
        }
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
