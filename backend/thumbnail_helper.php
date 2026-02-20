<?php
// backend/thumbnail_helper.php - Utility for generating video thumbnails

function getFFmpegPath() {
    // Local copy of FFmpeg for accessibility
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg.exe';
    if (file_exists($path)) return $path;
    return null;
}

function generateRandomThumbnail($videoId, $videoRelativePath, $pdo) {
    $ffmpeg = getFFmpegPath();
    if (!$ffmpeg) return "Error: FFmpeg not found";

    $thumbnailDir = __DIR__ . '/../uploads/thumbnails/';
    if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0777, true);

    $thumbnailFilename = 'auto_thumb_' . $videoId . '_' . uniqid() . '.png';
    $thumbnailPath = $thumbnailDir . $thumbnailFilename;
    
    // Convert relative path to absolute for FFmpeg
    $absVideoPath = $videoRelativePath;
    if (strpos($videoRelativePath, 'http') !== 0) {
        $absVideoPath = realpath(__DIR__ . '/..' . $videoRelativePath);
        if (!$absVideoPath) return "Error: Video file not found locally ($videoRelativePath)";
    }

    // 1. Get video duration
    $duration = 5; 
    $cmdDuration = escapeshellarg($ffmpeg) . " -i " . escapeshellarg($absVideoPath) . " 2>&1";
    exec($cmdDuration, $outputDuration);
    foreach ($outputDuration as $line) {
        if (preg_match('/Duration: ((\d+):(\d+):(\d+))/', $line, $matches)) {
            $h = (int)$matches[2]; 
            $m = (int)$matches[3]; 
            $s = (int)$matches[4];
            $duration = ($h * 3600) + ($m * 60) + $s;
            break;
        }
    }

    // 2. Pick a random time
    $randomTime = rand(1, max(1, $duration - 1));
    
    // 3. Extract the frame (as PNG)
    $cmd = escapeshellarg($ffmpeg) . " -ss $randomTime -i " . escapeshellarg($absVideoPath) . " -vframes 1 " . escapeshellarg($thumbnailPath) . " 2>&1";
    exec($cmd, $output);
    
    if (file_exists($thumbnailPath)) {
        $thumbnailUrl = 'uploads/thumbnails/' . $thumbnailFilename;
        $stmt = $pdo->prepare("UPDATE videos SET thumbnail_url = ? WHERE id = ?");
        $stmt->execute([$thumbnailUrl, $videoId]);
        return $thumbnailUrl;
    }
    
    return "Error: FFmpeg execution failed. Output: " . (count($output) > 0 ? $output[0] : "No output from command: $cmd");
}

function runThumbnailCleanup($pdo) {
    $stmt = $pdo->query("SELECT id, video_url, thumbnail_url FROM videos");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    $details = [];

    foreach ($videos as $v) {
        $shouldFix = false;
        if (empty($v['thumbnail_url'])) {
            $status = generateRandomThumbnail($v['id'], $v['video_url'], $pdo);
            $details[] = "ID " . $v['id'] . ": " . $status;
            if (strpos($status, '/uploads/') === 0) $count++;
        }
    }
    return ['count' => $count, 'details' => $details];
}
