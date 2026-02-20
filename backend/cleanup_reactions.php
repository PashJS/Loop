<?php
// backend/cleanup_reactions.php - One-time cleanup script to remove duplicate reactions
header('Content-Type: application/json');
session_start();
require 'config.php';

// Only allow admin or logged-in user to run this
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login.']);
    exit;
}

try {
    // Find and remove duplicates, keeping only the most recent one per user/comment
    // First, find all user/comment pairs that have more than 1 reaction
    $stmt = $pdo->query("
        SELECT user_id, comment_id, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY created_at DESC) as ids
        FROM comment_reactions 
        GROUP BY user_id, comment_id 
        HAVING cnt > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deletedCount = 0;
    
    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        // Keep the first one (most recent), delete the rest
        $keepId = array_shift($ids);
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM comment_reactions WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $deletedCount += count($ids);
        }
    }
    
    // Also enforce the unique constraint if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE comment_reactions ADD UNIQUE KEY unique_user_comment_reaction (user_id, comment_id)");
    } catch (PDOException $e) {
        // Constraint might already exist, that's fine
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Cleanup complete. Removed $deletedCount duplicate reactions.",
        'duplicates_found' => count($duplicates),
        'deleted' => $deletedCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
