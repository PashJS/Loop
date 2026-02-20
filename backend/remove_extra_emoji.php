<?php
require_once 'config.php';
try {
    // Check if both columns exist
    $stmt = $pdo->query("DESCRIBE comment_reactions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('emoji', $columns) && in_array('reaction_type', $columns)) {
        // Drop the extra 'emoji' column as 'reaction_type' is used in the code
        $pdo->exec("ALTER TABLE comment_reactions DROP COLUMN emoji");
        echo "Successfully dropped redundant 'emoji' column from comment_reactions.";
    } else {
        echo "Redundant 'emoji' column not found or already removed.";
    }
} catch (Exception $e) {
    echo "Error fixing table: " . $e->getMessage();
}
?>
