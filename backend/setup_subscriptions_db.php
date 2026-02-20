<?php
require 'config.php';

try {
    // 1. Create Subscriptions Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscriber_id INT NOT NULL,
            subscribed_to INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subscribed_to) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_subscription (subscriber_id, subscribed_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Subscriptions table checked/created.<br>";

    // 2. Add Bio Column
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL");
        echo "Bio column added to users table.<br>";
    } else {
        echo "Bio column already exists.<br>";
    }

    echo "Database setup completed successfully.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
