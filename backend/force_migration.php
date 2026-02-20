<?php
require_once __DIR__ . '/config.php';

try {
    echo "Checking database schema...\n";
    
    // Check is_delivered
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_delivered'");
    if (!$stmt->fetch()) {
        echo "Adding is_delivered column...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
        echo "Column added.\n";
    } else {
        echo "is_delivered column already exists.\n";
    }
    
    // Check is_read
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
    if (!$stmt->fetch()) {
        echo "Adding is_read column...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    } else {
        echo "is_read column exists.\n";
    }

    echo "Migration complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
