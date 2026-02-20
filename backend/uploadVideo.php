<?php
// Manual Session ID Handling for Web (Cross-Origin) - MUST BE BEFORE ANY SESSION START
$receivedSessionId = $_GET['session_id'] ?? ($_POST['session_id'] ?? ($_REQUEST['session_id'] ?? null));
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}

// backend/uploadVideo.php - Upload video file and create video entry
require_once 'cors.php';
header('Content-Type: application/json');
session_start();
require 'config.php';

// Log request headers and method for debugging 401
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
$logFile = $logDir . '/upload_debug.log';
file_put_contents($logFile, "\n[" . date('Y-m-d H:i:s') . "] Upload Request Received:\n", FILE_APPEND);
file_put_contents($logFile, "Received Session ID: " . $receivedSessionId . "\n", FILE_APPEND);
file_put_contents($logFile, "Final Session ID: " . session_id() . "\n", FILE_APPEND);
$headers = getallheaders();
$cookie = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'No Cookie';

file_put_contents($logFile, "\n[" . date('Y-m-d H:i:s') . "] Upload Request Received:\n", FILE_APPEND);
file_put_contents($logFile, "Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($logFile, "Cookie: " . $cookie . "\n", FILE_APPEND);
file_put_contents($logFile, "Session ID Before Start: " . session_id() . "\n", FILE_APPEND);
file_put_contents($logFile, "User ID in Session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL') . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $debug = [
        'received_id' => $receivedSessionId,
        'actual_id' => session_id(),
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'has_user_id' => isset($_SESSION['user_id']),
        'params' => $_GET,
        'cookie_header' => $_SERVER['HTTP_COOKIE'] ?? 'NONE'
    ];
    file_put_contents($logFile, "Error: User not logged in (401). Debug: " . json_encode($debug) . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid. Please log out and log back in.',
        'debug' => $debug
    ]);
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No video file uploaded or upload error.'
    ]);
    exit;
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$hashtags = isset($_POST['hashtags']) ? json_decode($_POST['hashtags'], true) : [];

if (empty($title)) {
    echo json_encode([
        'success' => false,
        'message' => 'Title is required.'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/videos/';
$thumbnailDir = __DIR__ . '/../uploads/thumbnails/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!is_dir($thumbnailDir)) {
    mkdir($thumbnailDir, 0777, true);
}

// Validate file type
$allowedTypes = [
    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
    'video/x-msvideo', 'video/x-matroska', 'video/x-flv', 
    'video/x-ms-wmv', 'video/x-m4v', 'video/3gpp', 'video/mp2t'
];
$fileType = $_FILES['video']['type'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid video format. Allowed: MP4, WebM, OGG, MOV. Received: ' . $fileType
    ]);
    exit;
}

// Validate file size (max 500MB)
$maxSize = 500 * 1024 * 1024; // 500MB
if ($_FILES['video']['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Video file is too large. Maximum size is 500MB.'
    ]);
    exit;
}

try {
    // Generate unique filename
    $extension = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('video_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file to videos folder
    if (!move_uploaded_file($_FILES['video']['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    // Use filesystem path for video URL
    $videoUrl = '/uploads/videos/' . $filename;
    
    // Handle thumbnail
    $thumbnailUrl = null;
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        // User uploaded a thumbnail
        $allowedThumbTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $thumbType = $_FILES['thumbnail']['type'];
        
        if (in_array($thumbType, $allowedThumbTypes)) {
            $thumbExtension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $thumbnailFilename = uniqid('thumb_', true) . '.' . $thumbExtension;
            $thumbnailPath = $thumbnailDir . $thumbnailFilename;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbnailPath)) {
                $thumbnailUrl = '/uploads/thumbnails/' . $thumbnailFilename;
            }
        }
    }
    
    // If no thumbnail uploaded, generate one from the video
    if (!$thumbnailUrl) {
        require_once 'thumbnail_helper.php';
        // We need to wait until the video is in the database to get an ID for generateRandomThumbnail,
        // or we can generate it first and update later.
        // Let's modify the flow to generate it AFTER the DB insert.
    }
    
    // Insert video into database
    $isClip = isset($_POST['is_clip']) && $_POST['is_clip'] === 'true' ? 1 : 0;
    
    $stmt = $pdo->prepare("
        INSERT INTO videos (title, description, video_url, thumbnail_url, user_id, status, views, is_clip)
        VALUES (?, ?, ?, ?, ?, 'published', 0, ?)
    ");
    
    $result = $stmt->execute([
        $title,
        $description ?: '',
        $videoUrl,
        $thumbnailUrl,
        $_SESSION['user_id'],
        $isClip
    ]);
    
    if ($result) {
        $videoId = $pdo->lastInsertId();
        
        // If no thumbnail was provided, generate one now using the Video ID
        if (empty($thumbnailUrl)) {
            $newThumb = generateRandomThumbnail($videoId, $videoUrl, $pdo);
            if ($newThumb) $thumbnailUrl = $newThumb;
        }
        
        // Fetch the created video with its (possibly updated) thumbnail
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
        
        // Log Success
        file_put_contents($logFile, "Upload Success: Video ID " . $videoId . "\n", FILE_APPEND);

        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully!',
            'video' => [
                'id' => (int)$video['id'],
                'title' => $video['title'],
                'description' => $video['description'],
                'video_url' => $video['video_url'],
                'thumbnail_url' => $video['thumbnail_url'],
                'views' => (int)$video['views'],
                'created_at' => $video['created_at'],
                'author' => [
                    'id' => (int)$video['user_id'],
                    'username' => $video['username']
                ]
            ]
        ]);
    } else {
        // Clean up files if video insert failed
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        if ($thumbnailUrl && file_exists($thumbnailDir . basename($thumbnailUrl))) {
            unlink($thumbnailDir . basename($thumbnailUrl));
        }
        throw new Exception('Failed to save video to database.');
    }
} catch (Exception $e) {
    http_response_code(500);
    file_put_contents($logFile, "Upload Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload video: ' . $e->getMessage()
    ]);
}
?>
