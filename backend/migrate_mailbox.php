<?php
require 'config.php';

try {
    // 1. Update Notifications ENUM
    // Note: altering enums can be tricky if data exists, but adding to the end is usually safe-ish.
    // However, repeated calls might error if already exists, but here we just redefine the list.
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('comment_like', 'comment_reply', 'video_like', 'video_comment', 'subscription', 'comment_reaction', 'video_save', 'video_love', 'security_alert') NOT NULL");
    echo "Updated notifications type ENUM.\n";

    // 2. Create Mailbox Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_mb (user_id),
        INDEX idx_created_mb (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created mailbox_logs table.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Unknown column 'type'") !== false) {
       // if it fails, maybe table empty? ignore for now or handle
       echo "Enum update warning: " . $e->getMessage() . "\n";
    } else {
       echo "Database Error: " . $e->getMessage() . "\n";
    }
}
