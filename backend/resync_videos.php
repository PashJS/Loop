<?php
// backend/resync_videos.php - Automatically import video files into the database
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only allow through browser if logged in, or CLI
if (php_sapi_name() !== 'cli' && !isset($_SESSION['user_id'])) {
    die("Access denied. Please login first.");
}

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
if ($userId === 0) {
    // Try to find the first user in the DB as a fallback for CLI
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $userId = $stmt->fetchColumn();
}

if (!$userId) {
    die("No user found to assign videos to. Please register first.");
}

$videoDir = __DIR__ . '/../uploads/videos/';
$thumbDir = __DIR__ . '/../uploads/thumbnails/';

$videos = glob($videoDir . "*.{mp4,webm,mov}", GLOB_BRACE);
$count = 0;

echo "Scanning $videoDir...\n";

foreach ($videos as $videoPath) {
    $filename = basename($videoPath);
    
    // Check if already in DB
    $stmt = $pdo->prepare("SELECT id FROM videos WHERE video_url LIKE ?");
    $stmt->execute(['%/uploads/videos/' . $filename]);
    
    if (!$stmt->fetch()) {
        $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
        $videoUrl = '/uploads/videos/' . $filename;
        
        // Try to find matching thumbnail
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $thumbFilename = null;
        $possibleThumbs = glob($thumbDir . $basename . ".*");
        if (!empty($possibleThumbs)) {
            $thumbFilename = '/uploads/thumbnails/' . basename($possibleThumbs[0]);
        }
        
        $insert = $pdo->prepare("INSERT INTO videos (user_id, title, description, video_url, thumbnail_url, status, views) VALUES (?, ?, ?, ?, ?, 'published', ?)");
        $randomViews = mt_rand(10, 500);
        $insert->execute([$userId, $title, "Automatically restored video.", $videoUrl, $thumbFilename, $randomViews]);
        
        echo "Imported: $filename\n";
        $count++;
    }
}

echo "Done! Imported $count new videos for user ID $userId.\n";
?>
