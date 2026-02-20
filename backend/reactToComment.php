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

if (!isset($data['comment_id']) || !isset($data['reaction'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$comment_id = (int)$data['comment_id'];
$reaction = trim($data['reaction']);

try {
    // Check if user already reacted
    $stmt = $pdo->prepare("SELECT reaction_type FROM comment_reactions WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$user_id, $comment_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $reaction) {
            $pdo->prepare("DELETE FROM comment_reactions WHERE user_id = ? AND comment_id = ?")->execute([$user_id, $comment_id]);
        } else {
            $pdo->prepare("UPDATE comment_reactions SET reaction_type = ? WHERE user_id = ? AND comment_id = ?")->execute([$reaction, $user_id, $comment_id]);
        }
    } else {
        $pdo->prepare("INSERT INTO comment_reactions (user_id, comment_id, reaction_type) VALUES (?, ?, ?)")->execute([$user_id, $comment_id, $reaction]);
    }

    // Get updated counts for this specific comment
    $countsStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM comment_reactions WHERE comment_id = ? GROUP BY reaction_type");
    $countsStmt->execute([$comment_id]);
    $reactions = $countsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's current reaction
    $userReactionStmt = $pdo->prepare("SELECT reaction_type FROM comment_reactions WHERE user_id = ? AND comment_id = ?");
    $userReactionStmt->execute([$user_id, $comment_id]);
    $userReaction = $userReactionStmt->fetchColumn() ?: null;

    echo json_encode([
        'success' => true, 
        'reactions' => $reactions, 
        'user_reaction' => $userReaction
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
