<?php
// backend/check_emoji_encoding.php - Check raw emoji bytes in database
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login.']);
    exit;
}

try {
    // Check character set
    $charset = $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch();
    $collation = $pdo->query("SHOW VARIABLES LIKE 'collation_connection'")->fetch();
    
    // Get raw emoji data with hex values
    $stmt = $pdo->query("
        SELECT id, comment_id, user_id, emoji, HEX(emoji) as emoji_hex, LENGTH(emoji) as emoji_len
        FROM comment_reactions
        ORDER BY id DESC
        LIMIT 20
    ");
    $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check table collation
    $tableInfo = $pdo->query("SHOW CREATE TABLE comment_reactions")->fetch();
    
    echo json_encode([
        'success' => true,
        'connection_charset' => $charset,
        'connection_collation' => $collation,
        'table_definition' => $tableInfo[1] ?? 'N/A',
        'reactions' => $reactions
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
