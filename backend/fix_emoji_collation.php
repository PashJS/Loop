<?php
// backend/fix_emoji_collation.php - Fix the emoji column collation
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login.']);
    exit;
}

try {
    // Change the emoji column to use binary collation
    // This ensures that each unique emoji is treated as distinct
    $pdo->exec("ALTER TABLE comment_reactions MODIFY emoji VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
    
    // Verify the change
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM comment_reactions WHERE Field = 'emoji'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test the grouping now
    $stmt = $pdo->prepare("SELECT emoji, COUNT(*) as cnt FROM comment_reactions WHERE comment_id = 17 GROUP BY emoji");
    $stmt->execute();
    $testResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Emoji column collation changed to utf8mb4_bin',
        'column_info' => $column,
        'test_group_by_comment_17' => $testResult
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
