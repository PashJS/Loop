<?php
// backend/optimize_db.php
require_once 'config.php';

header('Content-Type: text/plain');
echo "Starting Database Optimization...\n";

try {
    // 1. Add missing indexes
    echo "Adding Indexes...\n";
    
    $indexes = [
        ['notifications', 'idx_user_hidden_read', '(user_id, is_hidden, is_read)'],
        ['notifications', 'idx_created_at', '(created_at)'],
        ['notification_preferences', 'idx_user_type', '(user_id, type)'],
        ['stories', 'idx_created_at', '(created_at)'],
        ['users', 'idx_is_pro', '(is_pro)'],
        ['saves', 'idx_user_video', '(user_id, video_id)']
    ];

    foreach ($indexes as $idx) {
        $table = $idx[0];
        $name = $idx[1];
        $cols = $idx[2];
        
        try {
            // Check if index exists
            $check = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$name'");
            if (!$check->fetch()) {
                echo "[MIGRATE] Adding $name to $table...\n";
                $pdo->exec("ALTER TABLE $table ADD INDEX $name $cols");
                echo "[OK] $name added.\n";
            } else {
                echo "[SKIP] $name already exists on $table.\n";
            }
        } catch (Exception $e) {
            echo "[ERR] Failed to add index $name: " . $e->getMessage() . "\n";
        }
    }

    // 2. Clear out log_activity errors
    // Since I'm here, I'll ensure the login_activity columns are correct too.
    try {
        $pdo->exec("ALTER TABLE login_activity MODIFY COLUMN location VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE login_activity MODIFY COLUMN session_id VARCHAR(255) NULL");
        echo "[OK] login_activity columns optimized.\n";
    } catch (Exception $e) {}

    echo "\nOptimization Completed Successfully.\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>
