<?php
// Run this once to add bio column to users table
require 'config.php';

try {
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL");
        echo "Bio column added successfully!";
    } else {
        echo "Bio column already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

