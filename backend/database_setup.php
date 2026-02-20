<?php
// backend/database_setup.php - Standalone migration and setup script
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "Starting Database Setup...\n";

try {
    // 1. Messages Table
    echo "Processing 'messages' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL DEFAULT 0,
        group_id INT DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_approved TINYINT(1) DEFAULT 0,
        is_delivered TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        reactions JSON DEFAULT NULL,
        reply_to TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sender_id),
        INDEX (receiver_id),
        INDEX (group_id),
        INDEX (is_approved)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migrations for 'messages'
    $cols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
    $existingCols = array_map('strtolower', $cols);

    if (!in_array('is_approved', $existingCols)) {
        echo "Adding 'is_approved' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
    }
    if (!in_array('reply_to', $existingCols)) {
        echo "Adding 'reply_to' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN reply_to TEXT DEFAULT NULL");
    }
    if (!in_array('group_id', $existingCols)) {
        echo "Adding 'group_id' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id, ADD INDEX (group_id)");
    }
    if (!in_array('is_deleted', $existingCols)) {
        echo "Adding 'is_deleted' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    }
    if (!in_array('reactions', $existingCols)) {
        echo "Adding 'reactions' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN reactions JSON DEFAULT NULL");
    }
    if (!in_array('is_delivered', $existingCols)) {
        echo "Adding 'is_delivered' to messages...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
    }

    // 2. Groups Table (using backticks for reserved word)
    echo "Processing 'groups' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        picture VARCHAR(255),
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Group Members Table
    echo "Processing 'group_members' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('member', 'admin') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(group_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "\nDatabase Setup Completed Successfully!\n";

} catch (PDOException $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
}
?>
