<?php
require 'config.php';
try {
    echo "Fixing collations...\n";
    $pdo->exec("ALTER TABLE notification_preferences CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
