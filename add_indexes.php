<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=floxwatch", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $queries = [
        "ALTER TABLE login_activity ADD INDEX idx_user_sess (user_id, session_id)",
        "ALTER TABLE notifications ADD INDEX idx_user_hidden_read (user_id, is_hidden, is_read)",
        "ALTER TABLE stories ADD INDEX idx_created_at (created_at)"
    ];
    
    foreach ($queries as $q) {
        try {
            echo "Running: $q\n";
            $pdo->exec($q);
        } catch (PDOException $e) {
            // Error 1061 is "Duplicate key name"
            if (strpos($e->getMessage(), '1061') !== false) {
                echo "Index already exists, skipping.\n";
            } else {
                echo "ERROR on query: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "SUCCESS: Indexing process complete.\n";
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>
