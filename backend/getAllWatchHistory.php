<?php
// backend/getAllWatchHistory.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            wp.progress_seconds, 
            wp.duration_seconds, 
            wp.updated_at,
            v.id as video_id, 
            v.title, 
            v.thumbnail_url as thumbnail_path, 
            u.username as channel_name,
            u.profile_picture as channel_avatar
        FROM watch_progress wp
        JOIN videos v ON wp.video_id = v.id
        JOIN users u ON v.user_id = u.id
        WHERE wp.user_id = ?
        ORDER BY wp.updated_at DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $rawHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history = array_map(function($item) {
        $t = $item['thumbnail_path'];
        if ($t && strpos($t, 'http') !== 0) {
            $t = (strpos($t, '..') === 0) ? $t : '../' . ltrim($t, '/');
        }
        $item['thumbnail_path'] = $t;
        
        $a = $item['channel_avatar'];
        if ($a && strpos($a, 'http') !== 0) {
            $a = (strpos($a, '..') === 0) ? $a : '../' . ltrim($a, '/');
        }
        $item['channel_avatar'] = $a;
        
        return $item;
    }, $rawHistory);

    echo json_encode(['success' => true, 'history' => $history]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
