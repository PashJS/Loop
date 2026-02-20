<?php
require 'config.php';
$vttFiles = glob(__DIR__ . '/../uploads/videos/*.vtt');
echo "Found " . count($vttFiles) . " VTT files on server.\n";
$updated = 0;
foreach($vttFiles as $file) {
    $basename = basename($file);
    $vttBase = pathinfo($basename, PATHINFO_FILENAME);
    
    // Search for video where video_url contains the same base filename
    $pattern = '%' . $vttBase . '%';
    $stmt = $pdo->prepare("SELECT id, title, video_url, captions_url FROM videos WHERE video_url LIKE ?");
    $stmt->execute([$pattern]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($videos as $video) {
        $url = '/uploads/videos/' . $basename;
        if ($video['captions_url'] !== $url) {
            $update = $pdo->prepare("UPDATE videos SET captions_url = ? WHERE id = ?");
            $update->execute([$url, $video['id']]);
            echo "LINKED: ID {$video['id']} ({$video['title']}) -> {$basename}\n";
            $updated++;
        } else {
            echo "ALREADY LINKED: ID {$video['id']} ({$video['title']})\n";
        }
    }
}
echo "Done. Total newly linked: $updated\n";
?>
