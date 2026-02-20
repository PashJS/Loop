<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            u.username, 
            u.profile_picture,
            u.is_pro,
            ps.comment_badge,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as is_liked,
            (SELECT reaction_type FROM post_reactions WHERE post_id = p.id AND user_id = ? LIMIT 1) as user_reaction
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN pro_settings ps ON u.id = ps.user_id
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format URLs
    foreach ($posts as &$post) {
        $post['is_liked'] = (bool)$post['is_liked'];
        if ($post['profile_picture'] && strpos($post['profile_picture'], 'http') !== 0) {
            $post['profile_picture'] = '../' . $post['profile_picture'];
        }
        if ($post['image_path'] && strpos($post['image_path'], 'http') !== 0) {
            $post['image_path'] = '../' . $post['image_path'];
        }
    }

    echo json_encode(['success' => true, 'posts' => $posts]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
