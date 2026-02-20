<?php
require 'config.php';
$vttFiles = glob(__DIR__ . '/../uploads/videos/*.vtt');
$updated = 0;
foreach($vttFiles as $file) {
    $basename = basename($file);
    $videoBasename = str_replace('.vtt', '', $basename);
    
    // Search for video with matching URL base
    $pattern = '%' . $videoBasename . '%';
    $stmt = $pdo->prepare("SELECT id, title, captions_url FROM videos WHERE video_url LIKE ?");
    $stmt->execute([$pattern]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($video) {
        $url = '/uploads/videos/' . $basename;
        if ($video['captions_url'] !== $url) {
            $update = $pdo->prepare("UPDATE videos SET captions_url = ? WHERE id = ?");
            $update->execute([$url, $video['id']]);
            echo "Linked {$basename} to Video ID {$video['id']} ({$video['title']})\n";
            $updated++;
        }
    }
}
echo "Total linked: $updated\n";
?>
