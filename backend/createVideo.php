<?php
// backend/createVideo.php - Create a new video
header('Content-Type: application/json');
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to upload videos.'
    ]);
    exit;
}

// Get request payload
$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

// Validate input
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$video_url = trim($input['video_url'] ?? '');
$thumbnail_url = trim($input['thumbnail_url'] ?? '');

if (empty($title) || empty($video_url)) {
    echo json_encode([
        'success' => false,
        'message' => 'Title and video URL are required.'
    ]);
    exit;
}

if (strlen($title) > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'Title must be 100 characters or less.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO videos (title, description, video_url, thumbnail_url, user_id, status, views)
        VALUES (?, ?, ?, ?, ?, 'published', 0)
    ");
    
    $result = $stmt->execute([
        $title,
        $description ?: '',
        $video_url,
        $thumbnail_url ?: null,
        $_SESSION['user_id']
    ]);
    
    if ($result) {
        $videoId = $pdo->lastInsertId();
        
        // Fetch the created video with user info
        $stmt = $pdo->prepare("
            SELECT 
                v.id,
                v.title,
                v.description,
                v.video_url,
                v.thumbnail_url,
                v.views,
                v.user_id,
                v.created_at,
                u.username
            FROM videos v
            INNER JOIN users u ON v.user_id = u.id
            WHERE v.id = ?
        ");
        $stmt->execute([$videoId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully!',
            'video' => [
                'id' => (int)$video['id'],
                'title' => $video['title'],
                'description' => $video['description'],
                'video_url' => $video['video_url'],
                'thumbnail_url' => $video['thumbnail_url'] ?: '/frontend/assets/default-thumbnail.jpg',
                'views' => (int)$video['views'],
                'created_at' => $video['created_at'],
                'author' => [
                    'id' => (int)$video['user_id'],
                    'username' => $video['username']
                ]
            ]
        ]);
    } else {
        throw new Exception('Failed to create video.');
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload video. Please try again.',
        'error' => $e->getMessage()
    ]);
}
?>
