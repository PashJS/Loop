<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['post_id']) || !isset($data['reaction'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$post_id = (int)$data['post_id'];
$reaction = trim($data['reaction']);

try {
    // Check if user already reacted
    $stmt = $pdo->prepare("SELECT reaction_type FROM post_reactions WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $reaction) {
            $pdo->prepare("DELETE FROM post_reactions WHERE user_id = ? AND post_id = ?")->execute([$user_id, $post_id]);
        } else {
            $pdo->prepare("UPDATE post_reactions SET reaction_type = ? WHERE user_id = ? AND post_id = ?")->execute([$reaction, $user_id, $post_id]);
        }
    } else {
        $pdo->prepare("INSERT INTO post_reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)")->execute([$user_id, $post_id, $reaction]);
    }

    // Get updated counts
    $countsStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM post_reactions WHERE post_id = ? GROUP BY reaction_type");
    $countsStmt->execute([$post_id]);
    $reactions = $countsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's current reaction
    $userReactionStmt = $pdo->prepare("SELECT reaction_type FROM post_reactions WHERE user_id = ? AND post_id = ?");
    $userReactionStmt->execute([$user_id, $post_id]);
    $userReaction = $userReactionStmt->fetchColumn() ?: null;

    echo json_encode(['success' => true, 'reactions' => $reactions, 'user_reaction' => $userReaction]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
