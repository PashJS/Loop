<?php
ob_start();
header('Content-Type: application/json');
session_start();
require_once 'config.php';
ob_clean();

if (!isset($_GET['post_id'])) {
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit;
}

$post_id = (int)$_GET['post_id'];
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

try {
    // Self-healing: Ensure is_pinned column exists in post_comments
    $columns = $pdo->query("SHOW COLUMNS FROM post_comments LIKE 'is_pinned'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE post_comments ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER parent_id");
    }

    // Self-healing: Ensure comment_reactions table uses reaction_type instead of emoji (or both)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            reaction_type VARCHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment_reaction (user_id, comment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Ensure reaction_type column exists if table was created previously with 'emoji'
    $colCheck = $pdo->query("SHOW COLUMNS FROM comment_reactions LIKE 'reaction_type'")->fetchAll();
    if (empty($colCheck)) {
        $pdo->exec("ALTER TABLE comment_reactions ADD COLUMN reaction_type VARCHAR(32) NOT NULL AFTER user_id");
        $pdo->exec("UPDATE comment_reactions SET reaction_type = emoji WHERE emoji IS NOT NULL");
    }

    // Ensure likes/dislikes tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_comment_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (comment_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS post_comment_dislikes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (comment_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch ALL comments for this post
    $stmt = $pdo->prepare("
        SELECT 
            pc.*, 
            u.username, 
            u.profile_picture,
            u.is_pro,
            ps.comment_badge,
            ps.name_badge,
            pu.username as parent_username,
            (SELECT reaction_type FROM comment_reactions WHERE comment_id = pc.id AND user_id = ? LIMIT 1) as user_reaction,
            (SELECT COUNT(*) FROM post_comment_likes WHERE comment_id = pc.id) as likes_count,
            (SELECT COUNT(*) FROM post_comment_dislikes WHERE comment_id = pc.id) as dislikes_count,
            (SELECT COUNT(*) FROM post_comment_likes WHERE comment_id = pc.id AND user_id = ?) as is_liked,
            (SELECT COUNT(*) FROM post_comment_dislikes WHERE comment_id = pc.id AND user_id = ?) as is_disliked
        FROM post_comments pc
        JOIN users u ON pc.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        LEFT JOIN post_comments ppc ON pc.parent_id = ppc.id
        LEFT JOIN users pu ON ppc.user_id = pu.id
        WHERE pc.post_id = ?
        ORDER BY pc.is_pinned DESC, pc.created_at ASC
    ");
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $post_id]);
    $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allComments as &$c) {
        if ($c['profile_picture'] && strpos($c['profile_picture'], 'http') !== 0) {
            $c['profile_picture'] = '../' . $c['profile_picture'];
        }
        
        // Fetch reaction counts for this comment
        $rStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM comment_reactions WHERE comment_id = ? GROUP BY reaction_type");
        $rStmt->execute([$c['id']]);
        $c['reactions'] = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'comments' => $allComments]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
