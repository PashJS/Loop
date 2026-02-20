<?php
// backend/sync_captions_db.php
require 'config.php';

echo "Syncing captions in database...<br>\n";

$dir = __DIR__ . '/../uploads/videos/';
$files = glob($dir . '*.vtt');

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);
    // Corresponding video file (try common extensions)
    $videoNameBase = pathinfo($filename, PATHINFO_FILENAME);
    
    // Find video ID from filename if possible, or search by video_url
    // video_url in DB is like '/uploads/videos/video_xxx.mp4'
    
    // We can search for videos where video_url contains the base name
    $stmt = $pdo->prepare("SELECT id, video_url FROM videos WHERE video_url LIKE ?");
    $stmt->execute(['%' . $videoNameBase . '%']);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($video) {
        $vttUrl = '/uploads/videos/' . $filename;
        
        // Update DB
        $update = $pdo->prepare("UPDATE videos SET captions_url = ? WHERE id = ?");
        $update->execute([$vttUrl, $video['id']]);
        $count++;
        echo "Updated video ID {$video['id']} with captions: $vttUrl<br>\n";
    }
}

echo "Synced $count videos.<br>\n";
?>
