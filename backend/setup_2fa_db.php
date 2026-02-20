<?php
// backend/setup_2fa_db.php
require 'config.php';

try {
    $sql = file_get_contents('schema_2fa.sql');
    // PHP's PDO exec doesn't always handle multiple statements well depending on the driver
    // So we'll split them if needed, or just run it. 
    // Since it's MARIADB/MYSQL, most will work.
    
    // Split by semicolon and filter empty
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($commands as $command) {
        if (!empty($command)) {
            $pdo->exec($command);
        }
    }

    echo "Database updated successfully for 2FA.";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}
