<?php
// backend/raw_sql_test.php - Direct SQL test
header('Content-Type: text/plain; charset=utf-8');
session_start();
require 'config.php';

echo "=== Direct SQL Test ===\n\n";

try {
    // Test 1: Raw data for comment 17
    echo "1. All reactions for comment 17:\n";
    $stmt = $pdo->prepare("SELECT * FROM comment_reactions WHERE comment_id = 17");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   ID: {$row['id']}, User: {$row['user_id']}, Emoji: {$row['emoji']}\n";
    }
    
    // Test 2: GROUP BY for comment 17
    echo "\n2. GROUP BY for comment 17:\n";
    $stmt = $pdo->prepare("SELECT emoji, COUNT(*) as cnt FROM comment_reactions WHERE comment_id = 17 GROUP BY emoji");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   Emoji: {$row['emoji']}, Count: {$row['cnt']}\n";
    }
    
    // Test 3: Total count
    echo "\n3. Total count for comment 17:\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM comment_reactions WHERE comment_id = 17");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Total: {$total['total']}\n";
    
    // Test 4: Check for comment 118 too
    echo "\n4. All reactions for comment 118:\n";
    $stmt = $pdo->prepare("SELECT * FROM comment_reactions WHERE comment_id = 118");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   ID: {$row['id']}, User: {$row['user_id']}, Emoji: {$row['emoji']}\n";
    }
    
    echo "\n5. GROUP BY for comment 118:\n";
    $stmt = $pdo->prepare("SELECT emoji, COUNT(*) as cnt FROM comment_reactions WHERE comment_id = 118 GROUP BY emoji");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   Emoji: {$row['emoji']}, Count: {$row['cnt']}\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
