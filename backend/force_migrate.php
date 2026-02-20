<?php
// backend/force_migrate.php
require_once 'config.php';

header('Content-Type: text/plain');

echo "Starting Force Migration...\n";

try {
    // 1. Check/Add xpoints
    try {
        $pdo->query("SELECT xpoints FROM users LIMIT 1");
        echo "[OK] xpoints column exists.\n";
    } catch (PDOException $e) {
        echo "[MIGRATE] Adding xpoints to users...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN xpoints INT DEFAULT 100 AFTER id");
        echo "[OK] xpoints added.\n";
    }

    // 2. Check/Add saves
    try {
        $pdo->query("SELECT 1 FROM saves LIMIT 1");
        echo "[OK] saves table exists.\n";
    } catch (Exception $e) {
        echo "[MIGRATE] Creating saves table...\n";
        $pdo->exec("CREATE TABLE saves (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, video_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (user_id, video_id)) ENGINE=InnoDB");
        echo "[OK] saves created.\n";
    }

    // 3. Check/Add user_switch_tokens
    try {
        $pdo->query("SELECT 1 FROM user_switch_tokens LIMIT 1");
        echo "[OK] user_switch_tokens table exists.\n";
    } catch (Exception $e) {
        echo "[MIGRATE] Creating user_switch_tokens table...\n";
        $pdo->exec("CREATE TABLE user_switch_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, expires_at TIMESTAMP NULL) ENGINE=InnoDB");
        echo "[OK] user_switch_tokens created.\n";
    }

    // 4. Check/Add marketplace
    try {
        $pdo->query("SELECT 1 FROM market_extensions LIMIT 1");
        echo "[OK] market_extensions exists.\n";
    } catch (Exception $e) {
        echo "[MIGRATE] Creating market_extensions and related tables...\n";
        $pdo->exec("CREATE TABLE market_extensions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, extension_id VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, price INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        echo "[OK] market_extensions created.\n";
    }

    echo "Migration Completed Successfully.\n";

} catch (Exception $e) {
    echo "FATAL ERROR DURING MIGRATION: " . $e->getMessage() . "\n";
}
?>
