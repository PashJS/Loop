<?php
// backend/run_fix_thumbnails.php - Trigger thumbnail cleanup for all videos
header('Content-Type: application/json');
require 'config.php';
require 'thumbnail_helper.php';

try {
    $result = runThumbnailCleanup($pdo);
    echo json_encode([
        'success' => true,
        'message' => "Processed videos.",
        'fixed_count' => $result['count'],
        'details' => $result['details']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
