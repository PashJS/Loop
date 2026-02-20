<?php
// backend/check_file_storage.php - Check if file_storage table exists
require 'config.php';

header('Content-Type: application/json');

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'file_storage'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo json_encode([
            'success' => false,
            'message' => 'file_storage table does not exist. Please run the SQL setup in phpmyadmin.sql',
            'sql_needed' => "
CREATE TABLE IF NOT EXISTS file_storage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_type ENUM('video', 'thumbnail', 'profile_picture', 'other') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    file_data LONGBLOB NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    user_id INT NULL,
    related_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_file_type (file_type),
    INDEX idx_user_id (user_id),
    INDEX idx_related_id (related_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ]);
        exit;
    }
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE file_storage");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'file_storage table exists',
        'columns' => array_column($columns, 'Field')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

