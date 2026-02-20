<?php
// backend/fix_watchlist_schema.php
require 'config.php';

try {
    $pdo->exec("USE floxwatch;");

    // 1. Ensure 'favorites' table exists (for the Heart button)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_favorite (user_id, video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Create 'saves' table (for the Bookmark button)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_save (user_id, video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Create 'watch_progress' table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS watch_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            progress_seconds FLOAT NOT NULL DEFAULT 0,
            duration_seconds FLOAT NOT NULL DEFAULT 0,
            is_watched TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_progress (user_id, video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Watchlist tables created/verified successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
