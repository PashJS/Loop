<?php
require_once 'config.php';

try {
    // Add profile_visibility column with default 'public'
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_visibility VARCHAR(20) DEFAULT 'public'");
    echo "Column 'profile_visibility' added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'profile_visibility' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
