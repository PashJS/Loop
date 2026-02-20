<?php
// backend/get_active_announcement.php - Get active poll for users
header('Content-Type: application/json');
session_start();
require 'config.php';

$userId = $_SESSION['user_id'] ?? null;

try {
    // Check if tables exist
    try {
        $pdo->query("SELECT 1 FROM announcements LIMIT 1");
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'announcement' => null]);
        exit;
    }
    
    // Get active announcement
    $stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$announcement) {
        echo json_encode(['success' => true, 'announcement' => null]);
        exit;
    }
    
    // Get options
    $optStmt = $pdo->prepare("SELECT id, label FROM announcement_options WHERE announcement_id = ?");
    $optStmt->execute([$announcement['id']]);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user already voted
    $hasVoted = false;
    $userVoteOption = null;
    if ($userId) {
        $voteCheck = $pdo->prepare("SELECT option_id FROM announcement_votes WHERE announcement_id = ? AND user_id = ?");
        $voteCheck->execute([$announcement['id'], $userId]);
        $vote = $voteCheck->fetch(PDO::FETCH_ASSOC);
        if ($vote) {
            $hasVoted = true;
            $userVoteOption = (int)$vote['option_id'];
        }
    }
    
    // Get vote counts if user has voted
    $totalVotes = 0;
    if ($hasVoted) {
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM announcement_votes WHERE announcement_id = ?");
        $totalStmt->execute([$announcement['id']]);
        $totalVotes = (int)$totalStmt->fetchColumn();
        
        foreach ($options as &$opt) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM announcement_votes WHERE option_id = ?");
            $countStmt->execute([$opt['id']]);
            $opt['votes'] = (int)$countStmt->fetchColumn();
            $opt['percentage'] = $totalVotes > 0 ? round(($opt['votes'] / $totalVotes) * 100) : 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'announcement' => [
            'id' => (int)$announcement['id'],
            'context' => $announcement['context'],
            'options' => $options,
            'has_voted' => $hasVoted,
            'user_vote_option' => $userVoteOption,
            'total_votes' => $totalVotes
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
