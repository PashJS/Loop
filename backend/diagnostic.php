<?php
// diagnostic.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require 'config.php';
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    echo "Config loaded and collation connection set.\n";
    
    $userId = 1; // Assuming a test user ID
    if (isset($_SESSION['user_id'])) $userId = $_SESSION['user_id'];
    
    echo "Testing Notifications table...\n";
    $stmt = $pdo->query("DESCRIBE notifications");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    echo "\nTesting Preferences table...\n";
    $stmt = $pdo->query("DESCRIBE notification_preferences");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    echo "\nTesting Query...\n";
    $limit = 50;
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            u.username as actor_username,
            u.profile_picture as actor_profile_picture,
            CASE 
                WHEN n.target_type = 'video' THEN v1.thumbnail_url
                WHEN n.target_type = 'comment' THEN v2.thumbnail_url
                ELSE NULL
            END as video_thumbnail
        FROM notifications n
        WHERE n.user_id = ? 
        AND n.is_hidden = 0
        AND n.type NOT IN (SELECT value COLLATE utf8mb4_unicode_ci FROM notification_preferences WHERE user_id = ? AND type = 'hidden_type' COLLATE utf8mb4_unicode_ci)
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    echo "Query executed successfully.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
