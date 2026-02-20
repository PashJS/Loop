<?php
// backend/getVideoAnalytics.php - Get video view analytics over time periods
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

if (!isset($_GET['video_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Video ID is required.'
    ]);
    exit;
}

$videoId = (int)$_GET['video_id'];
$userId = $_SESSION['user_id'];

try {
    // Verify video belongs to user
    $stmt = $pdo->prepare("SELECT id FROM videos WHERE id = ? AND user_id = ?");
    $stmt->execute([$videoId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Video not found or access denied.'
        ]);
        exit;
    }
    
    // Create view_history table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS view_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            INDEX idx_video_id (video_id),
            INDEX idx_viewed_at (viewed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Get view history for the video
    $stmt = $pdo->prepare("
        SELECT DATE(viewed_at) as view_date, COUNT(*) as view_count
        FROM view_history
        WHERE video_id = ?
        GROUP BY DATE(viewed_at)
        ORDER BY view_date DESC
        LIMIT 90
    ");
    $stmt->execute([$videoId]);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by time periods (10-day periods)
    $analytics = [];
    $currentPeriod = null;
    $periodViews = 0;
    $periodStart = null;
    
    foreach ($rawData as $row) {
        $date = new DateTime($row['view_date']);
        $dayOfMonth = (int)$date->format('d');
        
        // Determine period (1-10, 11-20, 21-end of month)
        if ($dayOfMonth <= 10) {
            $period = $date->format('F') . ' 1-10';
            $periodStart = $date->format('Y-m-01');
            $periodEnd = $date->format('Y-m-10');
        } elseif ($dayOfMonth <= 20) {
            $period = $date->format('F') . ' 11-20';
            $periodStart = $date->format('Y-m-11');
            $periodEnd = $date->format('Y-m-20');
        } else {
            $period = $date->format('F') . ' 21-' . $date->format('t');
            $periodStart = $date->format('Y-m-21');
            $periodEnd = $date->format('Y-m-t');
        }
        
        if ($currentPeriod !== $period) {
            if ($currentPeriod !== null) {
                $analytics[] = [
                    'period' => $currentPeriod,
                    'views' => $periodViews,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd
                ];
            }
            $currentPeriod = $period;
            $periodViews = 0;
        }
        
        $periodViews += (int)$row['view_count'];
    }
    
    // Add last period
    if ($currentPeriod !== null) {
        $analytics[] = [
            'period' => $currentPeriod,
            'views' => $periodViews,
            'start_date' => $periodStart,
            'end_date' => $periodEnd
        ];
    }
    
    // Reverse to show oldest first
    $analytics = array_reverse($analytics);
    
    // Get total views
    $stmt = $pdo->prepare("SELECT views FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $totalViews = $stmt->fetch(PDO::FETCH_ASSOC)['views'];
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'total_views' => (int)$totalViews,
        'period_count' => count($analytics)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch analytics.',
        'error' => $e->getMessage()
    ]);
}
?>

