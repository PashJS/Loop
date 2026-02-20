<?php
require_once __DIR__ . '/config.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'xpoints'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $pdo->exec("ALTER TABLE users ADD COLUMN xpoints INT DEFAULT 0");
        echo "Successfully added 'xpoints' column to 'users' table.<br>";
    } else {
        echo "'xpoints' column already exists.<br>";
    }
    
    // Also ensure the transactions table exists
    $sql = "CREATE TABLE IF NOT EXISTS point_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        paypal_order_id VARCHAR(255) NOT NULL,
        amount_paid DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'USD',
        points_added INT NOT NULL,
        status VARCHAR(50) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Ensured 'point_transactions' table exists.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
