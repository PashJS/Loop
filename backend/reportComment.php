
<?php
// backend/reportComment.php
header('Content-Type: application/json');
session_start();
require 'config.php';
require 'mailer.php';

$data = json_decode(file_get_contents('php://input'), true);
$commentId = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
$reason = isset($data['reason']) ? trim($data['reason']) : '';
$details = isset($data['details']) ? trim($data['details']) : '';
$userId = $_SESSION['user_id'] ?? 0;

if ($commentId <= 0 || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    // Get comment details
    $stmt = $pdo->prepare("
        SELECT c.comment, u.username, v.title as video_title, v.id as video_id
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        INNER JOIN videos v ON c.video_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$commentId]);
    $commentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commentInfo) {
        echo json_encode(['success' => false, 'message' => 'Comment not found.']);
        exit;
    }

    $subject = "FloxWatch Comment Report: Comment #" . $commentId;
    $message = "
        <div style='font-family: sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #ff5252;'>New Comment Report</h2>
            <p><strong>Reported Comment ID:</strong> #{$commentId}</p>
            <p><strong>Video:</strong> {$commentInfo['video_title']} (ID: {$commentInfo['video_id']})</p>
            <p><strong>Comment Author:</strong> @{$commentInfo['username']}</p>
            <hr style='border: 1px solid #eee;'>
            <p style='font-style: italic; background: #f9f9f9; padding: 15px; border-radius: 8px;'>
                \"{$commentInfo['comment']}\"
            </p>
            <hr style='border: 1px solid #eee;'>
            <p><strong>Reporting Reason:</strong> {$reason}</p>
            <p><strong>Additional Details:</strong> " . ($details ?: 'None provided') . "</p>
            <p><strong>Reported By:</strong> " . ($userId ? "User #$userId" : "Guest") . "</p>
            <p style='margin-top: 30px; font-size: 12px; color: #999;'>FloxWatch Safety System</p>
        </div>
    ";

    $sent = sendFloxEmail('floxxteam@gmail.com', $subject, $message, true);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Report sent successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send report.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
