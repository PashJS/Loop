<?php
// backend/createNotification.php - Create a notification
// Note: This file is meant to be included via require_once, not called directly

function createNotification($pdo, $userId, $type, $actorId, $targetId, $targetType, $message) {
    try {
        // 1. Ensure Table & Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('comment_like', 'comment_reply', 'video_like', 'video_comment', 'subscription', 'comment_reaction', 'video_save', 'video_love', 'message_request') NOT NULL,
                actor_id INT NULL,
                target_id INT NULL,
                target_type ENUM('comment', 'video', 'comment_reply', 'message') NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                is_hidden TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_is_read (is_read),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Migration logic for existing tables
        try {
            $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('comment_like', 'comment_reply', 'video_like', 'video_comment', 'subscription', 'comment_reaction', 'video_save', 'video_love', 'message_request') NOT NULL");
            $pdo->exec("ALTER TABLE notifications MODIFY COLUMN target_type ENUM('comment', 'video', 'comment_reply', 'message') NULL");
            
            $stmtC = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'is_hidden'");
            if (!$stmtC->fetch()) {
                $pdo->exec("ALTER TABLE notifications ADD COLUMN is_hidden TINYINT(1) DEFAULT 0");
            }
        } catch (Exception $e) {}
        
        // 2. Insert Notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, actor_id, target_id, target_type, message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $type, $actorId, $targetId, $targetType, $message]);

        // 3. Optional Email Alert
        $stmtPref = $pdo->prepare("SELECT email, email_notifications FROM users WHERE id = ?");
        $stmtPref->execute([$userId]);
        $user = $stmtPref->fetch(PDO::FETCH_ASSOC);

        if ($user && (int)$user['email_notifications'] === 1) {
            require_once 'mailer.php';
            $subject = "FloxWatch Notification: " . ucfirst(str_replace('_', ' ', $type));
            $emailBody = "
                <div style='font-family: sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #ff4d4d;'>FloxWatch Alert</h2>
                    <p style='font-size: 16px; color: #333;'>$message</p>
                    <a href='http://localhost/FloxWatch/frontend/home.php' style='display: inline-block; padding: 10px 20px; background: #ff4d4d; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>View on FloxWatch</a>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #777;'>You are receiving this because email notifications are enabled in your settings.</p>
                </div>
            ";
            sendFloxEmail($user['email'], $subject, $emailBody, true);
        }

        return true;
    } catch (PDOException $e) {
        error_log('Notification creation error: ' . $e->getMessage());
        return false;
    }
}
?>
