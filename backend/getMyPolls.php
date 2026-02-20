<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
        (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id = p.id) as total_votes
        FROM polls p 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($polls as &$poll) {
        $stmtOpt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ?");
        $stmtOpt->execute([$poll['id']]);
        $poll['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'polls' => $polls]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
