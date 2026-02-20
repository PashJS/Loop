<?php
// backend/migrate_gifts.php
require_once __DIR__ . '/config.php';

try {
    // 1. Add columns to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pro_expires_at DATETIME NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pro_gifts_count INT DEFAULT 3");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_gifted_pro TINYINT(1) DEFAULT 0");

    // 2. Create pro_gifts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS pro_gifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo json_encode(['success' => true, 'message' => 'Migration successful']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
}
?>
