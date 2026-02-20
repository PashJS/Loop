<?php
require_once 'config.php';

try {
    // Disable foreign key checks to allow dropping/truncating
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear old data that might violate the new constraint
    $pdo->exec("TRUNCATE TABLE comment_reactions");
    
    // Drop all constraints on comment_reactions
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'comment_reactions' 
        AND TABLE_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME != 'PRIMARY'
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $c) {
        $name = $c['CONSTRAINT_NAME'];
        try { $pdo->exec("ALTER TABLE comment_reactions DROP FOREIGN KEY $name"); } catch(Exception $e) {}
    }

    // Ensure table structure is correct
    $pdo->exec("ALTER TABLE comment_reactions MODIFY user_id INT NOT NULL");
    $pdo->exec("ALTER TABLE comment_reactions MODIFY comment_id INT NOT NULL");
    
    // Check if reaction_type column exists, if not add it
    try {
        $pdo->query("SELECT reaction_type FROM comment_reactions LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE comment_reactions ADD COLUMN reaction_type VARCHAR(20) NOT NULL");
    }

    // Add correctly pointed foreign key
    $pdo->exec("ALTER TABLE comment_reactions ADD CONSTRAINT fk_comment_reactions_post_comments FOREIGN KEY (comment_id) REFERENCES post_comments(id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE comment_reactions ADD CONSTRAINT fk_comment_reactions_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Successfully wiped comment_reactions and fixed foreign keys to post_comments.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
