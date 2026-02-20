<?php
// backend/repair_orphans.php - Admin utility to promote orphaned replies to top-level
header('Content-Type: application/json');
require 'config.php';

try {
    // Safety: require explicit confirm=1 parameter (POST or GET) to perform repair
    $confirm = isset($_REQUEST['confirm']) && (string)$_REQUEST['confirm'] === '1';
    $videoId = isset($_REQUEST['video_id']) ? (int)$_REQUEST['video_id'] : null;

    $sql = "SELECT r.id FROM comments r LEFT JOIN comments p ON r.parent_id = p.id WHERE r.parent_id IS NOT NULL AND p.id IS NULL";
    $params = [];
    if ($videoId) { $sql .= " AND r.video_id = ?"; $params[] = $videoId; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orphans = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!$confirm) {
        echo json_encode(['success' => true, 'action' => 'preview', 'orphans_count' => count($orphans), 'orphans' => $orphans, 'message' => 'Run again with confirm=1 to promote these replies to top-level (parent_id=NULL)']);
        exit;
    }

    if (empty($orphans)) {
        echo json_encode(['success' => true, 'action' => 'none', 'message' => 'No orphaned replies found']);
        exit;
    }

    // Perform update in a transaction
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($orphans), '?'));
    $updateSql = "UPDATE comments SET parent_id = NULL WHERE id IN ($placeholders)";
    $ustmt = $pdo->prepare($updateSql);
    $ustmt->execute($orphans);
    $affected = $ustmt->rowCount();
    $pdo->commit();

    // Log repair
    $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/replies.log', sprintf("%s\tREPAIR_ORPHANS\tvideo=%s\trepaired=%d\torphans=%s\n", date('c'), $videoId ?? 'all', $affected, implode(',', $orphans)), FILE_APPEND | LOCK_EX);

    echo json_encode(['success' => true, 'action' => 'repair', 'repaired' => $affected, 'orphans' => $orphans]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
