<?php
// backend/fix_perf.php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=floxwatch", "root", "");
    $pdo->exec("ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_user_hidden_read (user_id, is_hidden, is_read)");
    $pdo->exec("ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_created_at (created_at)");
    $pdo->exec("ALTER TABLE notification_preferences ADD INDEX IF NOT EXISTS idx_user_type (user_id, type)");
    $pdo->exec("ALTER TABLE stories ADD INDEX IF NOT EXISTS idx_created_at (created_at)");
    echo "SUCCESS: Indexes added.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
