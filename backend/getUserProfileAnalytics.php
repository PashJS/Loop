<?php
// backend/getUserProfileAnalytics.php - Aggregate user content analytics
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 1. Get user's videos
    $stmt = $pdo->prepare("SELECT id, created_at FROM videos WHERE user_id = ?");
    $stmt->execute([$userId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $videoIds = array_column($videos, 'id');

    if (empty($videoIds)) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($videoIds), '?'));

    // 2. Fetch Views history
    $stmt = $pdo->prepare("
        SELECT DATE(viewed_at) as date, COUNT(*) as count 
        FROM view_history 
        WHERE video_id IN ($placeholders)
        GROUP BY DATE(viewed_at) ORDER BY date ASC LIMIT 30
    ");
    $stmt->execute($videoIds);
    $viewsHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Likes history
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM likes 
        WHERE video_id IN ($placeholders)
        GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30
    ");
    $stmt->execute($videoIds);
    $likesHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Comments history
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM comments 
        WHERE video_id IN ($placeholders)
        GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30
    ");
    $stmt->execute($videoIds);
    $commentsHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Hearts (Favorites) history
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM favorites 
        WHERE video_id IN ($placeholders)
        GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30
    ");
    $stmt->execute($videoIds);
    $heartsHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Saves (Bookmarks) history
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM saves 
        WHERE video_id IN ($placeholders)
        GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30
    ");
    $stmt->execute($videoIds);
    $savesHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Growth Rate (Very simple logic: ratio of likes/comments per view + consistency)
    $totalViews = array_sum(array_column($viewsHistory, 'count'));
    $totalEngagements = array_sum(array_column($likesHistory, 'count')) + array_sum(array_column($commentsHistory, 'count')) + array_sum(array_column($heartsHistory, 'count'));
    
    // Growth rate out of 10
    // Base 2 points for having content
    // + up to 4 points for engagement ratio
    // + up to 4 points for volume/frequency
    $engagementScore = $totalViews > 0 ? ($totalEngagements / $totalViews) * 20 : 0;
    $volumeScore = count($videoIds) / 5; // 20 videos = 4 points
    $growthRate = round(min(10, max(1, 2 + $engagementScore + $volumeScore)), 1);

    echo json_encode([
        'success' => true,
        'views' => $viewsHistory,
        'likes' => $likesHistory,
        'comments' => $commentsHistory,
        'hearts' => $heartsHistory,
        'saves' => $savesHistory,
        'growth_rate' => $growthRate,
        'video_count' => count($videoIds)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
