<?php
// backend/vote_announcement.php - Handle user votes on polls
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$announcementId = (int)($input['announcement_id'] ?? 0);
$optionId = (int)($input['option_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($announcementId <= 0 || $optionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Check if announcement is active
    $checkAnn = $pdo->prepare("SELECT id FROM announcements WHERE id = ? AND is_active = 1");
    $checkAnn->execute([$announcementId]);
    if (!$checkAnn->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Poll not found or inactive']);
        exit;
    }
    
    // Check if user already voted
    $checkVote = $pdo->prepare("SELECT id FROM announcement_votes WHERE announcement_id = ? AND user_id = ?");
    $checkVote->execute([$announcementId, $userId]);
    if ($checkVote->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already voted']);
        exit;
    }
    
    // Insert vote
    $stmt = $pdo->prepare("INSERT INTO announcement_votes (announcement_id, option_id, user_id) VALUES (?, ?, ?)");
    $stmt->execute([$announcementId, $optionId, $userId]);
    
    // Get updated results
    $totalVotes = $pdo->prepare("SELECT COUNT(*) FROM announcement_votes WHERE announcement_id = ?");
    $totalVotes->execute([$announcementId]);
    $total = (int)$totalVotes->fetchColumn();
    
    $optionsStmt = $pdo->prepare("
        SELECT ao.id, ao.label, COUNT(av.id) as votes 
        FROM announcement_options ao 
        LEFT JOIN announcement_votes av ON ao.id = av.option_id 
        WHERE ao.announcement_id = ? 
        GROUP BY ao.id
    ");
    $optionsStmt->execute([$announcementId]);
    $options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($options as &$opt) {
        $opt['percentage'] = $total > 0 ? round(($opt['votes'] / $total) * 100) : 0;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Vote recorded!',
        'total_votes' => $total,
        'options' => $options
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
