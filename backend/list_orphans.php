<?php
// backend/list_orphans.php - Admin utility to list orphaned replies
header('Content-Type: application/json');
require 'config.php';

try {
    $videoId = isset($_GET['video_id']) ? (int)$_GET['video_id'] : null;

    $sql = "SELECT r.id, r.parent_id, r.user_id, r.video_id, r.comment, r.created_at
            FROM comments r
            LEFT JOIN comments p ON r.parent_id = p.id
            WHERE r.parent_id IS NOT NULL AND p.id IS NULL";
    $params = [];
    if ($videoId) {
        $sql .= " AND r.video_id = ?";
        $params[] = $videoId;
    }

    $sql .= " ORDER BY r.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helpful extra: count total comments and total replies for the video
    if ($videoId) {
        $cst = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE video_id = ?");
        $cst->execute([$videoId]);
        $total = (int)$cst->fetch(PDO::FETCH_ASSOC)['cnt'];
    } else {
        $total = null;
    }

    // Log admin action
    $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/replies.log', sprintf("%s\tLIST_ORPHANS\tvideo=%s\tfound=%d\n", date('c'), $videoId ?? 'all', count($orphans)), FILE_APPEND | LOCK_EX);

    echo json_encode(['success' => true, 'video_id' => $videoId, 'total_comments' => $total, 'orphans_count' => count($orphans), 'orphans' => $orphans]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
